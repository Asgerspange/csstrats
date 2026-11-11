<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Faceit\FaceitExtendSocialite;
use Illuminate\Support\Facades\URL;
use Laravel\Paddle\Cashier as PaddleCashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PaddleCashier::useCustomerModel(\App\Models\PaddleCustomer::class);
        PaddleCashier::useSubscriptionModel(\App\Models\PaddleSubscription::class);
        \Laravel\Paddle\Cashier::useTransactionModel(\App\Models\PaddleTransaction::class);
        \Laravel\Paddle\Cashier::useSubscriptionItemModel(\App\Models\PaddleSubscriptionItem::class);
        Cashier::calculateTaxes();
        Cashier::useCustomerModel(User::class);
        ini_set('upload_max_filesize', '512M');
        ini_set('post_max_size', '512M');
        ini_set('memory_limit', '512M');

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('faceit', \SocialiteProviders\Faceit\Provider::class);
        });

        if($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Cashier::useSubscriptionModel(Subscription::class);
    }
}
