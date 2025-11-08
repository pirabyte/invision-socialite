<?php

namespace Pirabyte\InvisionSocialite;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use SocialiteProviders\Manager\Config;

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
            $config = \config('services.invision', []);

            $additionalConfig = [];
            if (isset($config['base_url'])) {
                $additionalConfig['base_url'] = $config['base_url'];
            }
            if (isset($config['scopes'])) {
                $additionalConfig['scopes'] = $config['scopes'];
            }

            $providerConfig = new Config(
                $config['client_id'] ?? '',
                $config['client_secret'] ?? '',
                $config['redirect'] ?? '',
                $additionalConfig
            );

            return (new InvisionProvider(
                $app['request'],
                null,
                null,
                null
            ))
                ->setConfig($providerConfig)
                ->setHttpClient($socialite->getHttpClient());
        });
    }
}
