<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('publishes protected resource metadata for the mcp endpoint', function () {
    $this->getJson('/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp'))
        ->assertJsonPath('scopes_supported.0', 'mcp:use');
});

test('registers an oauth client with a loopback redirect', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Hermes Agent',
        'redirect_uris' => ['http://127.0.0.1:45678/callback'],
    ])
        ->assertCreated()
        ->assertJsonPath('scope', 'mcp:use')
        ->assertJsonPath('redirect_uris.0', 'http://127.0.0.1:45678/callback')
        ->assertJsonStructure(['client_id']);
});

test('approving the consent redirects back with an authorization code', function () {
    $this->withoutVite();

    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Hermes Agent',
        'redirect_uris' => ['http://127.0.0.1:45678/callback'],
    ])->json('client_id');

    $this->actingAs(User::factory()->create());

    $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '=');

    $page = $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:45678/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-token',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))->assertOk();

    preg_match('/name="auth_token" value="([^"]+)"/', $page->getContent(), $matches);

    $this->post('/oauth/authorize', [
        'auth_token' => $matches[1],
        'client_id' => $clientId,
        'state' => 'state-token',
    ])
        ->assertRedirect()
        ->assertRedirectContains('http://127.0.0.1:45678/callback')
        ->assertRedirectContains('code=')
        ->assertRedirectContains('state=state-token');
});

test('login route points to the panel login', function () {
    $this->get('/login')->assertRedirect('/app/login');
});

test('rejects dynamic registration with a non loopback redirect', function () {
    $this->postJson('/oauth/register', [
        'client_name' => 'Evil',
        'redirect_uris' => ['https://evil.example/callback'],
    ])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');
});

test('consent screen shows the client and the scope', function () {
    $this->withoutVite();

    $clientId = $this->postJson('/oauth/register', [
        'client_name' => 'Hermes Agent',
        'redirect_uris' => ['http://127.0.0.1:45678/callback'],
    ])->json('client_id');

    $this->actingAs(User::factory()->create());

    $challenge = rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '=');

    $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'http://127.0.0.1:45678/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-token',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]))
        ->assertOk()
        ->assertSee('Autorizar Hermes Agent')
        ->assertSee('Usar o MCP do DiddyVisor');
});
