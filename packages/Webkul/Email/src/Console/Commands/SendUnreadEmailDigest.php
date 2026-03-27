<?php

namespace Webkul\Email\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Webkul\Email\Models\Email;
use Webkul\Email\Mails\UnreadEmailDigest as UnreadEmailDigestMail;
use Webkul\User\Models\User;

class SendUnreadEmailDigest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:send-unread-digest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a digest of all unread inbox emails to admin users.';

    /**
     * Handle.
     *
     * @return void
     */
    public function handle()
    {
        $unreadEmails = Email::where('is_read', false)
            ->whereNull('parent_id')
            ->whereJsonContains('folders', 'inbox')
            ->latest()
            ->get();

        if ($unreadEmails->isEmpty()) {
            $this->info('No unread inbox emails. Skipping digest.');

            return;
        }

        $adminUsers = User::where('status', 1)->get();

        foreach ($adminUsers as $user) {
            Mail::send(new UnreadEmailDigestMail($user, $unreadEmails));
        }

        $this->info("Unread email digest sent to {$adminUsers->count()} user(s) with {$unreadEmails->count()} unread email(s).");
    }
}
