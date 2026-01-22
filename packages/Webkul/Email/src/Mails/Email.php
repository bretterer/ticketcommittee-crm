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
        // Use the from address and name stored on the email record
        // These are set by EmailRepository based on tag mappings
        $fromAddress = $this->email->from;
        $fromName = $this->email->name ?? config('mail.from.name');

        $this->from($fromAddress, $fromName)
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
}
