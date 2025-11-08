<?php

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Pirabyte\InvisionSocialite\Provider;
use SocialiteProviders\Manager\Config;

class InvisionProviderTest extends TestCase
{
    public function test_redirect_builds_auth_url(): void
    {
        $request = Request::create('/', 'GET');

        $provider = new Provider(
            $request,
            'clientId',
            'secret',
            'https://app/callback'
        );

        $config = new Config(
            'clientId',
            'secret',
            'https://app/callback',
            ['base_url' => 'https://community.example.com']
        );

        $provider->setConfig($config);

        $response = $provider->stateless()->redirect();
        $url = $response->getTargetUrl();

        $this->assertStringContainsString('/oauth/authorize', $url);
        $this->assertStringContainsString('client_id=clientId', $url);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fapp%2Fcallback', $url);
        $this->assertStringContainsString('response_type=code', $url);
    }

    public function test_user_can_be_retrieved_from_token(): void
    {
        $request = Request::create('/', 'GET');

        // Mock HTTP response for user data
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 123,
                'member_id' => 123,
                'name' => 'John Doe',
                'full_name' => 'John Doe',
                'email' => 'john@example.com',
                'photo_url' => 'https://example.com/avatar.jpg',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new Provider(
            $request,
            'clientId',
            'secret',
            'https://app/callback'
        );

        $config = new Config(
            'clientId',
            'secret',
            'https://app/callback',
            ['base_url' => 'https://community.example.com']
        );

        $provider->setConfig($config);
        $provider->setHttpClient($client);

        $user = $provider->userFromToken('fake-token');

        $this->assertEquals(123, $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertEquals('https://example.com/avatar.jpg', $user->avatar);
    }

    public function test_oauth_token_exchange(): void
    {
        $request = Request::create('/callback', 'GET', ['code' => 'authorization-code']);

        // Mock HTTP responses for token exchange and user retrieval
        $mock = new MockHandler([
            // Token exchange response
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'access_token' => 'access-token-123',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ])),
            // User data response
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 456,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $provider = new Provider(
            $request,
            'clientId',
            'secret',
            'https://app/callback'
        );

        $config = new Config(
            'clientId',
            'secret',
            'https://app/callback',
            ['base_url' => 'https://community.example.com']
        );

        $provider->setConfig($config);
        $provider->setHttpClient($client);

        $user = $provider->stateless()->user();

        $this->assertEquals(456, $user->id);
        $this->assertEquals('Jane Smith', $user->name);
        $this->assertEquals('jane@example.com', $user->email);
    }
}
