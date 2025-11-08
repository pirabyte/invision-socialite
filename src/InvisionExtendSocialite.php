<?php

namespace Pirabyte\InvisionSocialite;

use SocialiteProviders\Manager\SocialiteWasCalled;

/**
 * Event listener for extending Socialite with Invision Community provider.
 *
 * This class is used when using the SocialiteProviders Manager package.
 */
class InvisionExtendSocialite
{
    /**
     * Handle the SocialiteWasCalled event.
     *
     * @param  SocialiteWasCalled  $socialiteWasCalled
     * @return void
     */
    public function handle(SocialiteWasCalled $socialiteWasCalled): void
    {
        $socialiteWasCalled->extendSocialite(
            'invision',
            InvisionProvider::class
        );
    }
}
