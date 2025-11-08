<?php

namespace Pirabyte\InvisionSocialite;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;

/**
 * Service provider for Invision Community Socialite integration.
 */
class InvisionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerProvider();
    }

    /**
     * Register the Invision Community provider with Socialite.
     *
     * @return void
     */
    protected function registerProvider(): void
    {
        /** @var SocialiteFactory $socialite */
        $socialite = $this->app->make(SocialiteFactory::class);

        $socialite->extend('invision', function ($app) use ($socialite) {
            $config = config('services.invision', []);

            return (new InvisionProvider(
                $app['request'],
                $config['client_id'] ?? null,
                $config['client_secret'] ?? null,
                $config['redirect'] ?? null,
                $config['base_url'] ?? null,
                $config['scopes'] ?? []
            ))->setHttpClient($socialite->getHttpClient());
        });
    }
}
