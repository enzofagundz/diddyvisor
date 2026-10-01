<?php

use App\Enums\MembershipRole;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

test('rejects unauthenticated requests with oauth discovery hint', function () {
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

    expect((string) $response->headers->get('WWW-Authenticate'))->toContain('resource_metadata=');
});

test('tools run as the authenticated user instead of the environment user', function () {
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
});

test('rejects a token without the mcp use scope', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, []);

    $this->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 3,
        'method' => 'tools/call',
        'params' => ['name' => 'list_houses', 'arguments' => (object) []],
    ])->assertForbidden();
});

test('accepts a personal access token with the mcp scope', function () {
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
});
