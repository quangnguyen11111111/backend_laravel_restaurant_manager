<?php

namespace App\Providers;

use App\Repositories\AuthRepository;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DishRepository;
use App\Repositories\GuestRepository;
use App\Repositories\TableRepository;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\DishRepositoryInterface;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use App\Services\Contracts\ImageStorageServiceInterface;
use App\Services\Contracts\MediaUploadServiceInterface;
use App\Services\PendingImageWorkflowService;
use App\Services\S3ImageStorageService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(AccountRepositoryInterface::class, AccountRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(DishRepositoryInterface::class, DishRepository::class);
        $this->app->bind(GuestRepositoryInterface::class, GuestRepository::class);
        $this->app->bind(TableRepositoryInterface::class, TableRepository::class);
        $this->app->bind(ImageStorageServiceInterface::class, S3ImageStorageService::class);
        $this->app->bind(MediaUploadServiceInterface::class, PendingImageWorkflowService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
