<?php

namespace Tests\Feature\Mcp;

use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Houses\ListHousesTool;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ListHousesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_with_configuration_error_when_mcp_user_is_missing(): void
    {
        config(['diddyvisor.mcp.user' => null]);

        DiddyVisorServer::tool(ListHousesTool::class)
            ->assertHasErrors()
            ->assertSee('"code":"configuration"');
    }

    public function test_lists_active_houses_of_the_configured_user(): void
    {
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa A']);
        $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $otherHouse = House::create(['name' => 'Casa B']);
        $otherHouse->memberships()->create(['user_id' => User::factory()->create()->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);

        config(['diddyvisor.mcp.user' => $user->email]);

        DiddyVisorServer::tool(ListHousesTool::class)
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('data.total', 1)
                ->where('data.next_offset', null)
                ->where('data.items.0.id', $house->id)
                ->where('data.items.0.name', 'Casa A')
                ->where('data.items.0.role', 'admin')
                ->etc());
    }
}
