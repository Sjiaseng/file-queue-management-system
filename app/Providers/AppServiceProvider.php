<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use App\Models\Upload;
use App\Observer\StatusObserver;

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
        Upload::observe(StatusObserver::class);
    }
}
