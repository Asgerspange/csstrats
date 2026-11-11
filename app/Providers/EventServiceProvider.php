<?php

namespace App\Providers;

use App\Models\{
    Team,
    Demo
};
use App\Observers\{
    TeamObserver,
    DemoObserver
};
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Cashier\Cashier;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
            \App\Listeners\AttributeReferral::class,
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
        Demo::observe(DemoObserver::class);
        // Ensure Cashier uses the correct Customer model (User in this case)
        Cashier::useCustomerModel(\App\Models\User::class);
    }
}
