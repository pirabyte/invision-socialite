<?php

namespace Pirabyte\InvisionSocialite;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Invision Community OAuth2 Provider for Laravel Socialite.
 *
 * This provider enables OAuth2 authentication with Invision Community (IPS) installations.
 */
class InvisionProvider extends AbstractProvider
{
    /**
     * The base URL of the Invision Community installation.
     */
    protected string $baseUrl;

    /**
     * The OAuth scopes to request.
     *
     * @var array<string>
     */
    protected array $scopes = [];

    /**
     * Create a new provider instance.
     *
     * @param  Request  $request
     * @param  string|null  $clientId
     * @param  string|null  $clientSecret
     * @param  string|null  $redirectUrl
     * @param  string|null  $baseUrl
     * @param  array<string>  $scopes
     */
    public function __construct(
        Request $request,
        ?string $clientId,
        ?string $clientSecret,
        ?string $redirectUrl,
        ?string $baseUrl,
        array $scopes = []
    ) {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl);
        $this->baseUrl = rtrim($baseUrl ?? '', '/');
        $this->scopes = $scopes ?: ['profile', 'email'];
    }

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $state
     * @return string
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase("{$this->baseUrl}/oauth/authorize", $state);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl(): string
    {
        return "{$this->baseUrl}/oauth/token";
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->request('GET', "{$this->baseUrl}/api/member/me", [
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
