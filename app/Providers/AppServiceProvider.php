<?php

namespace App\Providers;

use App\Models\OrderDetail;
use App\Models\Table;
use App\Models\Order;
use App\Patterns\Observer\OrderDetailObserver;
use App\Patterns\Observer\TableObserver;
use App\Patterns\Observer\OrderObserver;
use App\Repositories\AuthRepository;
use App\Repositories\AccountRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\DishRepository;
use App\Repositories\OrderDetailRepository;
use App\Repositories\GuestRepository;
use App\Repositories\OrderRepository;
use App\Repositories\TableRepository;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\DishRepositoryInterface;
use App\Repositories\Contracts\OrderDetailRepositoryInterface;
use App\Repositories\Contracts\GuestRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\TableRepositoryInterface;
use App\Patterns\Strategy\ImageStorage\ImageStorageStrategy;
use App\Services\Contracts\MediaUploadServiceInterface;
use App\Services\PendingImageWorkflowService;
use App\Patterns\Strategy\ImageStorage\S3ImageStorageStrategy;
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
        $this->app->bind(OrderDetailRepositoryInterface::class, OrderDetailRepository::class);
        $this->app->bind(GuestRepositoryInterface::class, GuestRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(TableRepositoryInterface::class, TableRepository::class);
        $this->app->bind(ImageStorageStrategy::class, S3ImageStorageStrategy::class);
        $this->app->bind(MediaUploadServiceInterface::class, PendingImageWorkflowService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        OrderDetail::attachGlobal(app(OrderDetailObserver::class));
        Table::attachGlobal(app(TableObserver::class));
        Order::attachGlobal(app(OrderObserver::class));
    }
}
