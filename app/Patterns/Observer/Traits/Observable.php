<?php

namespace App\Patterns\Observer\Traits;

use App\Patterns\Observer\Contracts\Observer;

trait Observable
{
    /**
     * @var array<string, array<Observer>>
     */
    protected static array $globalObservers = [];

    /**
     * @var array<Observer>
     */
    protected array $observers = [];

    /**
     * Gắn observer ở cấp độ toàn cục (Global) cho class
     * Giúp giải quyết vấn đề Model được khởi tạo liên tục trong Laravel
     */
    public static function attachGlobal(Observer $observer): void
    {
        static::$globalObservers[static::class][] = $observer;
    }

    /**
     * Gắn observer cho instance cụ thể
     */
    public function attach(Observer $observer): void
    {
        $this->observers[spl_object_hash($observer)] = $observer;
    }

    /**
     * Gỡ observer khỏi instance
     */
    public function detach(Observer $observer): void
    {
        unset($this->observers[spl_object_hash($observer)]);
    }

    /**
     * Thông báo cho tất cả observers (gồm instance và global)
     */
    public function notify(string $event = ''): void
    {
        // 1. Notify các observer của instance
        foreach ($this->observers as $observer) {
            $observer->update($this, $event);
        }

        // 2. Notify các observer toàn cục
        $globalObservers = static::$globalObservers[static::class] ?? [];
        foreach ($globalObservers as $observer) {
            $observer->update($this, $event);
        }
    }

    /**
     * Tự động được gọi bởi Laravel khi Model boot
     * Gắn các event lifecycle của Eloquent vào hàm notify() của GoF Pattern
     */
    public static function bootObservable(): void
    {
        static::created(function ($model) {
            $model->notify('created');
        });

        static::updated(function ($model) {
            $model->notify('updated');
        });

        static::deleted(function ($model) {
            $model->notify('deleted');
        });
    }
}
