<?php

namespace Webkul\Email\Repositories;

use Illuminate\Container\Container;
use Webkul\Core\Eloquent\Repository;
use Webkul\Email\Contracts\Email;

class EmailRepository extends Repository
{
    public function __construct(
        protected AttachmentRepository $attachmentRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    /**
     * Specify model class name.
     *
     * @return mixed
     */
    public function model()
    {
        return Email::class;
    }

    /**
     * Create.
     *
     * @return \Webkul\Email\Contracts\Email
     */
    public function create(array $data)
    {
        $uniqueId = time().'@'.config('mail.domain');

        $referenceIds = [];
        $parent = null;

        if (isset($data['parent_id'])) {
            $parent = parent::findOrFail($data['parent_id']);

            $referenceIds = $parent->reference_ids ?? [];
        }

        // Determine the from address based on parent email's tags
        $fromAddress = $this->getFromAddressFromTags($parent);

        $data = $this->sanitizeEmails(array_merge([
            'source'        => 'web',
            'from'          => $fromAddress,
            'user_type'     => 'admin',
            'folders'       => isset($data['is_draft']) ? ['draft'] : ['outbox'],
            'unique_id'     => $uniqueId,
            'message_id'    => $uniqueId,
            'reference_ids' => array_merge($referenceIds, [$uniqueId]),
        ], $data));

        $email = parent::create($data);

        $this->attachmentRepository->uploadAttachments($email, $data);

        return $email;
    }

    /**
     * Get the From address based on parent email's tags and auto-tag mappings.
     */
    protected function getFromAddressFromTags(?object $parentEmail): string
    {
        $defaultFrom = config('mail.from.address');

        if (! $parentEmail) {
            return $defaultFrom;
        }

        // Get tags from the parent email thread
        $tags = $parentEmail->tags;

        if ($tags->isEmpty()) {
            return $defaultFrom;
        }

        // Get auto-tag mappings config
        $mappingsConfig = core()->getConfigData('email.postmark.general.auto_tag_mappings');

        if (empty($mappingsConfig)) {
            return $defaultFrom;
        }

        $mappings = json_decode($mappingsConfig, true);

        if (! is_array($mappings) || empty($mappings)) {
            return $defaultFrom;
        }

        // Build a reverse lookup: tag_id -> email
        $tagIdToEmail = [];

        foreach ($mappings as $mapping) {
            if (! empty($mapping['email']) && ! empty($mapping['tag_id'])) {
                $tagIdToEmail[$mapping['tag_id']] = $mapping['email'];
            }
        }

        // Find first matching tag
        foreach ($tags as $tag) {
            if (isset($tagIdToEmail[$tag->id])) {
                return $tagIdToEmail[$tag->id];
            }
        }

        return $defaultFrom;
    }

    /**
     * Update.
     *
     * @param  int  $id
     * @param  string  $attribute
     * @return \Webkul\Email\Contracts\Email
     */
    public function update(array $data, $id, $attribute = 'id')
    {
        return parent::update($this->sanitizeEmails($data), $id);
    }

    /**
     * Sanitize emails.
     *
     * @return array
     */
    public function sanitizeEmails(array $data)
    {
        if (isset($data['reply_to'])) {
            $data['reply_to'] = array_values(array_filter($data['reply_to']));
        }

        if (isset($data['cc'])) {
            $data['cc'] = array_values(array_filter($data['cc']));
        }

        if (isset($data['bcc'])) {
            $data['bcc'] = array_values(array_filter($data['bcc']));
        }

        return $data;
    }
}
