<?php

namespace Pirabyte\InvisionSocialite;

use Illuminate\Support\Arr;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\Contracts\ConfigInterface;

/**
 * Invision Community OAuth2 Provider for Laravel Socialite.
 *
 * This provider enables OAuth2 authentication with Invision Community (IPS) installations.
 */
class InvisionProvider extends AbstractProvider
{
    /**
     * The additional config keys that are allowed to be retrieved from the config.
     *
     * @var array<string>
     */
    protected static array $additionalConfigKeys = ['base_url', 'scopes'];

    /**
     * The base URL of the Invision Community installation.
     */
    protected ?string $baseUrl = null;

    /**
     * The OAuth scopes to request.
     *
     * @var array<string>
     */
    protected $scopes = ['profile', 'email'];

    /**
     * Set the configuration for the provider.
     *
     * This method is called by the SocialiteProviders Manager to set additional config keys.
     *
     * @param  ConfigInterface  $config
     * @return $this
     */
    public function setConfig(ConfigInterface $config)
    {
        // Call parent to set client_id, client_secret, and redirect
        parent::setConfig($config);

        // Set additional config values
        $baseUrl = $this->getConfig('base_url');
        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        }

        $scopes = $this->getConfig('scopes');
        if (!empty($scopes)) {
            $this->scopes = $scopes;
        }

        return $this;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $state
     * @return string
     */
    protected function getAuthUrl($state): string
    {
        $baseUrl = $this->getBaseUrl();
        return $this->buildAuthUrlFromBase("{$baseUrl}/oauth/authorize", $state);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        $baseUrl = $this->getBaseUrl();
        return "{$baseUrl}/oauth/token";
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $baseUrl = $this->getBaseUrl();
        
        $response = $this->getHttpClient()->request('GET', "{$baseUrl}/api/member/me", [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        $body = $response->getBody()->getContents();
        $user = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Unable to parse user data from Invision Community API.');
        }

        return $user ?? [];
    }

    /**
     * Get the base URL from config or cached value.
     *
     * @return string
     * @throws \RuntimeException
     */
    protected function getBaseUrl(): string
    {
        if ($this->baseUrl !== null) {
            return $this->baseUrl;
        }

        $baseUrl = $this->getConfig('base_url');
        if (!$baseUrl) {
            throw new \RuntimeException('Base URL is not configured. Please set base_url in your services configuration.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        return $this->baseUrl;
    }

    /**
     * Map the user array to a Socialite User instance.
     *
     * @param  array<string, mixed>  $user
     * @return InvisionUser
     */
    protected function mapUserToObject(array $user): InvisionUser
    {
        return (new InvisionUser)->setRaw($user)->map([
            'id' => Arr::get($user, 'id') ?? Arr::get($user, 'member_id'),
            'nickname' => Arr::get($user, 'name'),
            'name' => Arr::get($user, 'full_name') ?? Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'photo_url'),
        ]);
    }

    /**
     * Get the code fields for the OAuth request.
     *
     * @param  string|null  $state
     * @return array<string, string>
     */
    protected function getCodeFields($state = null): array
    {
        return array_merge(parent::getCodeFields($state), [
            'scope' => implode(' ', $this->scopes),
        ]);
    }

    /**
     * Get the token fields for the token request.
     *
     * @param  string  $code
     * @return array<string, string>
     */
    protected function getTokenFields($code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
