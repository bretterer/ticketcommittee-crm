<?php

namespace Webkul\Email\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as MimeEmail;

class Email extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new email instance.
     *
     * @return void
     */
    public function __construct(public $email) {}

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $fromAddress = $this->getFromAddressFromTags();

        $this->from($fromAddress)
            ->to($this->email->reply_to)
            ->replyTo($this->email->parent_id ? $this->email->parent->unique_id : $this->email->unique_id)
            ->cc($this->email->cc ?? [])
            ->bcc($this->email->bcc ?? [])
            ->subject($this->email->parent_id ? $this->email->parent->subject : $this->email->subject)
            ->html($this->email->reply);

        $this->withSymfonyMessage(function (MimeEmail $message) {
            $message->getHeaders()->addIdHeader('Message-ID', $this->email->message_id);

            if ($this->email->parent_id && $this->email->parent->message_id) {
                $message->getHeaders()->addTextHeader('In-Reply-To', $this->email->parent->message_id);
            }

            $message->getHeaders()->addTextHeader('References', $this->email->parent_id
                ? implode(' ', $this->email->parent->reference_ids)
                : implode(' ', $this->email->reference_ids)
            );
        });

        foreach ($this->email->attachments as $attachment) {
            $this->attachFromStorage($attachment->path);
        }

        return $this;
    }

    /**
     * Get the From address based on email thread tags and auto-tag mappings.
     *
     * @return array|string
     */
    protected function getFromAddressFromTags()
    {
        // Get the thread root email (parent if replying, otherwise current)
        $threadEmail = $this->email->parent_id ? $this->email->parent : $this->email;

        // Get tags from the thread
        $tags = $threadEmail->tags;

        if ($tags->isEmpty()) {
            return $this->email->from;
        }

        // Get auto-tag mappings config
        $mappingsConfig = core()->getConfigData('email.postmark.general.auto_tag_mappings');

        if (empty($mappingsConfig)) {
            return $this->email->from;
        }

        $mappings = json_decode($mappingsConfig, true);

        if (! is_array($mappings) || empty($mappings)) {
            return $this->email->from;
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

        return $this->email->from;
    }
}
