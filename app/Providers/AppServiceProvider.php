<?php

namespace App\Providers;

use App\Models\RequiredDocument;
use App\Observers\RequiredDocumentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         RequiredDocument::observe(RequiredDocumentObserver::class);
    }
}
