<?php

namespace Webkul\Email\InboundEmailProcessor;

use Webkul\Email\Helpers\HtmlFilter;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Email\Repositories\EmailRepository;

class PostmarkEmailProcessor
{
    /**
     * Create a new processor instance.
     */
    public function __construct(
        protected EmailRepository $emailRepository,
        protected AttachmentRepository $attachmentRepository,
        protected HtmlFilter $htmlFilter
    ) {}

    /**
     * Process the inbound email from Postmark webhook.
     */
    public function processMessage(array $payload): void
    {
        // Use the actual email Message-ID header for threading (not Postmark's internal ID)
        // This is critical for email clients like Gmail to properly thread conversations
        $messageId = $this->getHeaderValue($payload, 'Message-ID')
            ?? $payload['MessageID']
            ?? null;

        if (! $messageId) {
            return;
        }

        // Check if email already exists
        $existingEmail = $this->emailRepository->findOneWhere(['message_id' => $messageId]);

        if ($existingEmail) {
            return;
        }

        // Parse email addresses
        $from = $this->parseEmailAddresses($payload['FromFull'] ?? null, true);
        $to = $this->parseEmailAddresses($payload['ToFull'] ?? []);
        $cc = $this->parseEmailAddresses($payload['CcFull'] ?? []);
        $bcc = $this->parseEmailAddresses($payload['BccFull'] ?? []);

        // Filter out Postmark inbound addresses from stored recipients
        // These addresses should not appear in reply-all
        $filteredTo = $this->filterPostmarkAddresses($to);
        $filteredCc = $this->filterPostmarkAddresses($cc);
        $filteredBcc = $this->filterPostmarkAddresses($bcc);

        // Get sender name
        $senderName = $payload['FromFull']['Name'] ?? '';

        if (empty($senderName) && ! empty($from)) {
            $senderName = $from[0];
        }

        // Parse headers for threading
        $inReplyTo = $this->getHeaderValue($payload, 'In-Reply-To');
        $references = $this->getHeaderValue($payload, 'References');

        $headers = [
            'from'          => $from,
            'sender'        => $from,
            'reply_to'      => $filteredTo,
            'cc'            => $filteredCc,
            'bcc'           => $filteredBcc,
            'subject'       => $payload['Subject'] ?? '',
            'name'          => $senderName,
            'source'        => 'email',
            'user_type'     => 'person',
            'message_id'    => $messageId,
            'reference_ids' => $references,
            'in_reply_to'   => $inReplyTo,
        ];

        // Get email body
        $reply = $payload['HtmlBody'] ?? $payload['TextBody'] ?? '';

        // Try to find parent email for threading (use original $to for lookup)
        $parentEmail = $this->findParentEmail($headers, $to);

        if (! $parentEmail) {
            // Create new email thread
            $email = $this->emailRepository->create(array_merge($headers, [
                'folders'       => ['inbox'],
                'reply'         => $reply,
                'unique_id'     => time().'@'.config('mail.domain'),
                'reference_ids' => [$messageId],
                'user_type'     => 'person',
            ]));

            $this->processAttachments($email, $payload['Attachments'] ?? []);
            $this->applyAutoTags($email, $to);
        } else {
            // Update parent email and create reply
            // Mark parent as unread so the thread appears bold in inbox
            $this->emailRepository->update([
                'folders'       => array_unique(array_merge($parentEmail->folders, ['inbox'])),
                'reference_ids' => array_merge($parentEmail->reference_ids ?? [], [$messageId]),
                'is_read'       => false,
            ], $parentEmail->id);

            $email = $this->emailRepository->create(array_merge($headers, [
                'reply'         => $this->htmlFilter->process($reply, ''),
                'parent_id'     => $parentEmail->id,
                'user_type'     => 'person',
            ]));

            $this->processAttachments($email, $payload['Attachments'] ?? []);

            // Apply auto-tags to parent email thread (not the reply)
            $this->applyAutoTags($parentEmail, $to);
        }
    }

    /**
     * Filter out Postmark inbound addresses from an array of emails.
     */
    protected function filterPostmarkAddresses(array $emails): array
    {
        return array_values(array_filter($emails, function ($email) {
            return ! str_ends_with(strtolower($email), '@inbound.postmarkapp.com');
        }));
    }

    /**
     * Parse email addresses from Postmark format.
     */
    protected function parseEmailAddresses(?array $data, bool $isSingle = false): array
    {
        if ($data === null) {
            return [];
        }

        // Handle single email (FromFull)
        if ($isSingle) {
            return isset($data['Email']) ? [$data['Email']] : [];
        }

        // Handle array of emails (ToFull, CcFull, etc.)
        $emails = [];

        foreach ($data as $item) {
            if (isset($item['Email']) && ! empty($item['Email'])) {
                $emails[] = $item['Email'];
            }
        }

        return $emails;
    }

    /**
     * Get header value from Postmark headers array.
     */
    protected function getHeaderValue(array $payload, string $headerName): ?string
    {
        $headers = $payload['Headers'] ?? [];

        foreach ($headers as $header) {
            if (strcasecmp($header['Name'] ?? '', $headerName) === 0) {
                return htmlspecialchars_decode($header['Value'] ?? '');
            }
        }

        return null;
    }

    /**
     * Find parent email for threading.
     */
    protected function findParentEmail(array $headers, array $toEmails): ?object
    {
        // Try to find by message_id in To field
        foreach ($toEmails as $to) {
            $email = $this->emailRepository->findOneWhere(['message_id' => $to]);

            if ($email) {
                return $email;
            }
        }

        // Try to find by In-Reply-To header
        if (! empty($headers['in_reply_to'])) {
            $email = $this->emailRepository->findOneWhere(['message_id' => $headers['in_reply_to']]);

            if ($email) {
                return $email;
            }

            $email = $this->emailRepository->findOneWhere([
                ['reference_ids', 'like', '%'.$headers['in_reply_to'].'%'],
            ]);

            if ($email) {
                return $email;
            }
        }

        // Try to find by References header
        if (! empty($headers['reference_ids'])) {
            $referenceIds = explode(' ', $headers['reference_ids']);

            foreach ($referenceIds as $referenceId) {
                $referenceId = trim($referenceId);

                if (empty($referenceId)) {
                    continue;
                }

                $email = $this->emailRepository->findOneWhere([
                    ['reference_ids', 'like', '%'.$referenceId.'%'],
                ]);

                if ($email) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * Process attachments from Postmark payload.
     */
    protected function processAttachments(object $email, array $attachments): void
    {
        if (empty($attachments)) {
            return;
        }

        foreach ($attachments as $attachment) {
            $this->attachmentRepository->uploadPostmarkAttachment($email, $attachment);
        }
    }

    /**
     * Apply auto-tags to email based on recipient address mappings.
     */
    protected function applyAutoTags(object $email, array $toEmails): void
    {
        $mappingsConfig = core()->getConfigData('email.postmark.general.auto_tag_mappings');

        if (empty($mappingsConfig)) {
            return;
        }

        // Parse mappings from JSON config
        $mappings = json_decode($mappingsConfig, true);

        if (! is_array($mappings) || empty($mappings)) {
            return;
        }

        // Build a lookup map of email -> tag_id
        $emailToTagId = [];

        foreach ($mappings as $mapping) {
            if (! empty($mapping['email']) && ! empty($mapping['tag_id'])) {
                $emailToTagId[strtolower($mapping['email'])] = $mapping['tag_id'];
            }
        }

        if (empty($emailToTagId)) {
            return;
        }

        // Find matching tags for recipient addresses
        $tagIds = [];

        foreach ($toEmails as $toEmail) {
            $toEmailLower = strtolower($toEmail);

            if (isset($emailToTagId[$toEmailLower])) {
                $tagId = $emailToTagId[$toEmailLower];

                if (! in_array($tagId, $tagIds)) {
                    $tagIds[] = $tagId;
                }
            }
        }

        // Attach tags to email (sync without detaching existing tags)
        if (! empty($tagIds)) {
            $email->tags()->syncWithoutDetaching($tagIds);
        }
    }
}
