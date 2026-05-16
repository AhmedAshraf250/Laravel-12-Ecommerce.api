<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
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
        // Event::listen(OrderShipped::class, SendShipmentNotification::class);
        // Gate::policy(Product::class, ProductPolicy::class);

        // Relation::morphMap([
        //     'post'  => \App\Models\Post::class,
        //     // 'video' => \App\Models\Video::class,
        // ]);
    }
}
