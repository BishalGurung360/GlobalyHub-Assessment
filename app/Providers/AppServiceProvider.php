<?php

namespace App\Providers;

use App\Contracts\NotificationServiceContract;
use App\Repositories\Contracts\NotificationRepositoryContract;
use App\Repositories\NotificationRepository;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NotificationServiceContract::class, NotificationService::class);
        $this->app->bind(NotificationRepositoryContract::class, NotificationRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
