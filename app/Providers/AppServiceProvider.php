<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Facebook\Provider as FacebookProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        View::composer('customer.partials.bottom-nav', function (ViewInstance $view): void {
            $user = auth()->user();

            $view->with(
                'hasUnreadInboxMessages',
                $user !== null && $user->customerNotifications()->whereNull('read_at')->exists(),
            );
        });

        Event::listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event): void {
            $event->extendSocialite('facebook', FacebookProvider::class);
            $event->extendSocialite('apple', AppleProvider::class);
        });
    }
}
