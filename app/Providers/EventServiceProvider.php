<?php

namespace App\Providers;

use App\Listeners\AutoVerifyPrivilegedUsers;
use App\Listeners\RecordFailedLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Spatie\Permission\Events\RoleAttached;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        RoleAttached::class => [
            AutoVerifyPrivilegedUsers::class,
        ],
        Failed::class => [
            RecordFailedLogin::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
