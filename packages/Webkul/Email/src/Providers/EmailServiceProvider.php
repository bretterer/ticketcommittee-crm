<?php

namespace Webkul\Email\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Webkul\Email\Console\Commands\ProcessInboundEmails;
use Webkul\Email\Http\Controllers\PostmarkWebhookController;
use Webkul\Email\Http\Middleware\VerifyPostmarkWebhook;
use Webkul\Email\InboundEmailProcessor\Contracts\InboundEmailProcessor;
use Webkul\Email\InboundEmailProcessor\SendgridEmailProcessor;
use Webkul\Email\InboundEmailProcessor\WebklexImapEmailProcessor;

class EmailServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');

        $this->registerRoutes();

        $this->app->bind(InboundEmailProcessor::class, function ($app) {
            $driver = config('mail-receiver.default');

            if ($driver === 'sendgrid') {
                return $app->make(SendgridEmailProcessor::class);
            }

            if ($driver === 'webklex-imap') {
                return $app->make(WebklexImapEmailProcessor::class);
            }

            throw new \Exception("Unsupported mail receiver driver [{$driver}].");
        });
    }

    /**
     * Register webhook routes.
     */
    protected function registerRoutes(): void
    {
        Route::post('webhooks/postmark/inbound', [PostmarkWebhookController::class, 'handle'])
            ->middleware(VerifyPostmarkWebhook::class)
            ->name('webhooks.postmark.inbound');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerCommands();
    }

    /**
     * Register the console commands of this package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessInboundEmails::class,
            ]);
        }
    }
}
