<?php

namespace Webkul\Email\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Webkul\Email\InboundEmailProcessor\PostmarkEmailProcessor;

class PostmarkWebhookController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PostmarkEmailProcessor $postmarkEmailProcessor
    ) {}

    /**
     * Handle inbound email webhook from Postmark.
     */
    public function handle(Request $request): Response
    {
        // Check if Postmark webhook is enabled
        if (! core()->getConfigData('email.postmark.general.enabled')) {
            return response('Postmark webhook is disabled', 403);
        }

        $payload = $request->all();

        // Process the inbound email
        $this->postmarkEmailProcessor->processMessage($payload);

        return response('OK', 200);
    }
}
