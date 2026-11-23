<?php

namespace App\Providers;

use App\Listeners\AuthActivitySubscriber;
use App\Settings\Settings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::subscribe(AuthActivitySubscriber::class);

        // In migrations: $table->userstamps();  → created_by, updated_by (FK users, never cascade-deleted)
        Blueprint::macro('userstamps', function () {
            /** @var Blueprint $this */
            $this->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
        });
    }
}
