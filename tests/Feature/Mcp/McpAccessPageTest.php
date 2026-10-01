<?php

use App\Enums\MembershipRole;
use App\Filament\Pages\McpAccess;
use App\Models\House;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('shows the hermes onboarding commands', function () {
    $this->withoutVite();

    [$user, $house] = mcpAccessHouse();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);

    Livewire::test(McpAccess::class)
        ->assertSee('hermes mcp add diddyvisor')
        ->assertSee('hermes mcp login diddyvisor')
        ->assertSee('hermes mcp test diddyvisor')
        ->assertSee(url('/mcp'));
});

test('generates a personal token with the mcp scope', function () {
    $this->withoutVite();

    [$user, $house] = mcpAccessHouse();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    app(ClientRepository::class)->createPersonalAccessGrantClient('DiddyVisor');

    Livewire::test(McpAccess::class)
        ->callAction('generateToken', data: ['name' => 'Hermes Agent'])
        ->assertHasNoActionErrors();

    $token = $user->tokens()->sole();
    expect($token->name)->toBe('Hermes Agent')
        ->and($token->scopes)->toContain('mcp:use');
});

test('revokes a token from the table', function () {
    $this->withoutVite();

    [$user, $house] = mcpAccessHouse();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    app(ClientRepository::class)->createPersonalAccessGrantClient('DiddyVisor');
    $token = $user->createToken('Hermes Agent', ['mcp:use'])->getToken();

    Livewire::test(McpAccess::class)
        ->callTableAction('revoke', $token)
        ->assertHasNoTableActionErrors();

    expect($user->tokens()->count())->toBe(0);
});

/**
 * @return array{0: User, 1: House}
 */
function mcpAccessHouse(): array
{
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa A']);
    $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);

    return [$user, $house];
}
