<?php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OAuthDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishes_protected_resource_metadata_for_the_mcp_endpoint(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('scopes_supported.0', 'mcp:use');
    }

    public function test_registers_an_oauth_client_with_a_loopback_redirect(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Hermes Agent',
            'redirect_uris' => ['http://127.0.0.1:45678/callback'],
        ])
            ->assertCreated()
            ->assertJsonPath('scope', 'mcp:use')
            ->assertJsonPath('redirect_uris.0', 'http://127.0.0.1:45678/callback')
            ->assertJsonStructure(['client_id']);
    }

    public function test_approving_the_consent_redirects_back_with_an_authorization_code(): void
    {
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
    }

    public function test_login_route_points_to_the_panel_login(): void
    {
        $this->get('/login')->assertRedirect('/app/login');
    }

    public function test_rejects_dynamic_registration_with_a_non_loopback_redirect(): void
    {
        $this->postJson('/oauth/register', [
            'client_name' => 'Evil',
            'redirect_uris' => ['https://evil.example/callback'],
        ])
            ->assertStatus(400)
            ->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_consent_screen_shows_the_client_and_the_scope(): void
    {
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
    }
}
