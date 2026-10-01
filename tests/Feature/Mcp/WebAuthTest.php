<?php

namespace Tests\Feature\Mcp;

use App\Enums\MembershipRole;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

class WebAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_unauthenticated_requests_with_oauth_discovery_hint(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => (object) [],
                'clientInfo' => ['name' => 'test', 'version' => '1'],
            ],
        ]);

        $response->assertUnauthorized();

        $this->assertStringContainsString(
            'resource_metadata=',
            (string) $response->headers->get('WWW-Authenticate'),
        );
    }

    public function test_tools_run_as_the_authenticated_user_instead_of_the_environment_user(): void
    {
        $user = User::factory()->create();
        $environmentUser = User::factory()->create();
        $userHouse = House::create(['name' => 'Casa da Ana']);
        $userHouse->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $environmentHouse = House::create(['name' => 'Casa do env']);
        $environmentHouse->memberships()->create(['user_id' => $environmentUser->id, 'display_name' => $environmentUser->name, 'role' => MembershipRole::Admin]);

        config(['diddyvisor.mcp.user' => $environmentUser->email]);

        Passport::actingAs($user, ['mcp:use']);

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'list_houses', 'arguments' => (object) []],
        ])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.total', 1)
            ->assertJsonPath('result.structuredContent.data.items.0.name', 'Casa da Ana');
    }

    public function test_rejects_a_token_without_the_mcp_use_scope(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user, []);

        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'list_houses', 'arguments' => (object) []],
        ])->assertForbidden();
    }

    public function test_accepts_a_personal_access_token_with_the_mcp_scope(): void
    {
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa da Ana']);
        $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        app(ClientRepository::class)->createPersonalAccessGrantClient('DiddyVisor');

        $plainTextToken = $user->createToken('Hermes Agent', ['mcp:use'])->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 4,
                'method' => 'tools/call',
                'params' => ['name' => 'list_houses', 'arguments' => (object) []],
            ])
            ->assertOk()
            ->assertJsonPath('result.structuredContent.data.items.0.name', 'Casa da Ana');
    }
}
