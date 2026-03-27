<?php

namespace Webkul\Email\Mails;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Webkul\User\Models\User;

class UnreadEmailDigest extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public Collection $unreadEmails,
    ) {}

    /**
     * Build the message.
     */
    public function build()
    {
        return $this
            ->to($this->user->email)
            ->subject('Krayin CRM - You have '.$this->unreadEmails->count().' unread email(s)')
            ->view('admin::emails.unread-digest', [
                'user'         => $this->user,
                'unreadEmails' => $this->unreadEmails,
            ]);
    }
}
