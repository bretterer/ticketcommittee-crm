<?php

namespace App\Providers;

use App\Http\Controllers\Quote\QuoteController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Override the quote print route to use letter-size paper
        // Use booted callback to ensure this runs after all service providers
        $this->app->booted(function () {
            Route::middleware(['web', 'admin_locale', 'user'])
                ->prefix(config('app.admin_path'))
                ->group(function () {
                    Route::get('quotes/print/{id?}', [QuoteController::class, 'print'])
                        ->name('admin.quotes.print');
                });
        });
    }
}
