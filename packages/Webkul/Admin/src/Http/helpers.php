<?php

if (! function_exists('bouncer')) {
    function bouncer()
    {
        return app()->make('bouncer');
    }
}

if (! function_exists('getUnreadInboxCount')) {
    /**
     * Get the count of unread emails in the inbox.
     */
    function getUnreadInboxCount(): int
    {
        return \Webkul\Email\Models\Email::query()
            ->where('is_read', false)
            ->whereNull('parent_id')
            ->whereJsonContains('folders', 'inbox')
            ->count();
    }
}
