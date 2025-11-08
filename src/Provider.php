<?php

namespace Pirabyte\InvisionSocialite;

use Illuminate\Support\Arr;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\Contracts\ConfigInterface;
use GuzzleHttp\RequestOptions;

/**
 * Invision Community OAuth2 Provider for Laravel Socialite.
 *
 * This provider enables OAuth2 authentication with Invision Community (IPS) installations.
 */
class Provider extends AbstractProvider
{
    /**
     * The additional config keys that are allowed to be retrieved from the config.
     *
     * @var array<string>
     */
    protected static array $additionalConfigKeys = ['base_url', 'scopes'];

    /**
     * The base URL of the Invision Community installation.
     *
     * @var string|null
     */
    protected ?string $baseUrl = null;

    /**
     * The OAuth scopes to request.
     *
     * @var array<string>
     */
    protected $scopes = ['profile', 'email'];

    /**
     * The separator used to split scopes in the token response.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Set the configuration for the provider.
     *
     * @param  ConfigInterface  $config
     * @return $this
     */
    public function setConfig(ConfigInterface $config)
    {
        parent::setConfig($config);

        // Get base_url from config
        $baseUrl = $this->getConfig('base_url');
        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        } else {
            // Fallback to Laravel config helper if available (for runtime use)
            if (function_exists('config')) {
                /** @phpstan-ignore-next-line */
                $baseUrl = \config('services.invision.base_url');
                if ($baseUrl) {
                    $this->baseUrl = rtrim($baseUrl, '/');
                }
            }
        }

        // Get scopes from config
        $scopes = $this->getConfig('scopes');
        if (!empty($scopes)) {
            $this->scopes = is_array($scopes) ? $scopes : explode(' ', $scopes);
        }

        return $this;
    }

    /**
     * Get the base URL from config or cached value.
     *
     * @return string
     * @throws \RuntimeException
     */
    protected function getBaseUrl(): string
    {
        if ($this->baseUrl !== null && $this->baseUrl !== '') {
            return $this->baseUrl;
        }

        // Try to get from config
        $baseUrl = $this->getConfig('base_url');
        
        // Fallback to Laravel config helper if available (for runtime use)
        if (!$baseUrl && function_exists('config')) {
            /** @phpstan-ignore-next-line */
            $baseUrl = \config('services.invision.base_url');
        }

        if (!$baseUrl) {
            throw new \RuntimeException('Base URL is not configured. Please set base_url in your services configuration.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        return $this->baseUrl;
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $state
     * @return string
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            $this->getBaseUrl() . '/oauth/authorize',
            $state
        );
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        // Try with trailing slash first - some servers require it
        return $this->getBaseUrl() . '/oauth/token/';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        // Based on Invision Community REST API docs, the endpoint for current user is /api/core/me
        // This endpoint returns user info when authenticated with profile and email scopes
        $url = $this->getBaseUrl() . '/api/core/me';
        
        $response = $this->getHttpClient()->get(
            $url,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                ],
            ]
        );

        $user = json_decode($response->getBody(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Unable to parse user data from Invision Community API.');
        }

        if (isset($user['errorCode'])) {
            throw new \RuntimeException(
                "Invision Community API error: {$user['errorCode']} - {$user['errorMessage']}"
            );
        }

        return $user ?? [];
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
        $fields = parent::getCodeFields($state);
        $fields['scope'] = implode(' ', $this->scopes);
        return $fields;
    }

    /**
     * Get the token fields for the token request.
     *
     * @param  string  $code
     * @return array<string, string>
     */
    protected function getTokenFields($code): array
    {
        return parent::getTokenFields($code);
    }

    /**
     * Get the headers for the access token request.
     *
     * @param  string  $code
     * @return array<string, string>
     */
    protected function getTokenHeaders($code): array
    {
        return ['Accept' => 'application/json'];
    }

    /**
     * Get the access token response for the given code.
     *
     * Override to ensure proper request format for Invision Community.
     *
     * @param  string  $code
     * @return array<string, mixed>
     */
    public function getAccessTokenResponse($code): array
    {
        $tokenUrl = $this->getTokenUrl();
        $tokenFields = $this->getTokenFields($code);

        try {
            // Use form_params - Guzzle will handle Content-Type automatically
            // But ensure we're using the post() method explicitly
            $client = $this->getHttpClient();
            
            // Create a new request with explicit POST method
            $response = $client->request('POST', $tokenUrl, [
                RequestOptions::FORM_PARAMS => $tokenFields,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                ],
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::ALLOW_REDIRECTS => false, // Don't follow redirects
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $decoded = json_decode($responseBody, true);

            if ($statusCode !== 200) {
                throw new \RuntimeException(
                    "Invision OAuth token request failed with status {$statusCode}: {$responseBody}"
                );
            }

            return $decoded ?? [];
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions (like the status code error above)
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions in a runtime exception with a clear message
            throw new \RuntimeException(
                "Invision OAuth token request failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
