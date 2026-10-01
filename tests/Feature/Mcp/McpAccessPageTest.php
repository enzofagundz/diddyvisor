<?php

namespace Tests\Feature\Mcp;

use App\Enums\MembershipRole;
use App\Filament\Pages\McpAccess;
use App\Models\House;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;
use Tests\TestCase;

class McpAccessPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_a_personal_token_with_the_mcp_scope(): void
    {
        $this->withoutVite();

        $user = $this->userInAHouse();
        app(ClientRepository::class)->createPersonalAccessGrantClient('DiddyVisor');

        Livewire::test(McpAccess::class)
            ->callAction('generateToken', data: ['name' => 'Hermes Agent'])
            ->assertHasNoActionErrors();

        $token = $user->tokens()->sole();
        $this->assertSame('Hermes Agent', $token->name);
        $this->assertContains('mcp:use', $token->scopes);
    }

    public function test_revokes_a_token_from_the_table(): void
    {
        $this->withoutVite();

        $user = $this->userInAHouse();
        app(ClientRepository::class)->createPersonalAccessGrantClient('DiddyVisor');
        $token = $user->createToken('Hermes Agent', ['mcp:use'])->getToken();

        Livewire::test(McpAccess::class)
            ->callTableAction('revoke', $token)
            ->assertHasNoTableActionErrors();

        $this->assertSame(0, $user->tokens()->count());
    }

    private function userInAHouse(): User
    {
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa A']);
        $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);

        return $user;
    }
}
