<?php

namespace Webkul\Email\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPostmarkWebhook
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $webhookToken = core()->getConfigData('email.postmark.general.webhook_token');

        // Skip verification if no token is configured
        if (empty($webhookToken)) {
            return $next($request);
        }

        // Check for basic authentication header
        $authHeader = $request->header('Authorization');

        if ($authHeader) {
            // Handle Basic Auth format: "Basic base64(username:password)"
            if (str_starts_with($authHeader, 'Basic ')) {
                $credentials = base64_decode(substr($authHeader, 6));
                $parts = explode(':', $credentials, 2);

                // Token can be username, password, or the full credentials
                if (count($parts) === 2 && ($parts[0] === $webhookToken || $parts[1] === $webhookToken)) {
                    return $next($request);
                }

                if ($credentials === $webhookToken) {
                    return $next($request);
                }
            }

            // Handle Bearer token format
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);

                if (hash_equals($webhookToken, $token)) {
                    return $next($request);
                }
            }
        }

        // Check for token in query string
        $queryToken = $request->query('token');

        if ($queryToken && hash_equals($webhookToken, $queryToken)) {
            return $next($request);
        }

        // Check for custom Postmark header (if you've configured it in Postmark)
        $postmarkToken = $request->header('X-Postmark-Token');

        if ($postmarkToken && hash_equals($webhookToken, $postmarkToken)) {
            return $next($request);
        }

        return response('Unauthorized', 401);
    }
}
