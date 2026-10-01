<?php

namespace Tests\Feature\Mcp;

use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Members\ListMembersTool;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ListMembersTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbids_a_user_who_is_not_a_member_of_the_house(): void
    {
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa A']);
        $house->memberships()->create(['user_id' => User::factory()->create()->id, 'display_name' => 'Bia', 'role' => MembershipRole::Admin]);

        config(['diddyvisor.mcp.user' => $user->email]);

        DiddyVisorServer::tool(ListMembersTool::class, ['house_id' => $house->id])
            ->assertHasErrors()
            ->assertSee('"code":"forbidden"');
    }

    public function test_lists_active_members_of_a_house_the_user_belongs_to(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $member = User::factory()->create(['name' => 'Bia']);
        $house = House::create(['name' => 'Casa A']);
        $house->memberships()->create(['user_id' => $user->id, 'display_name' => 'Ana', 'role' => MembershipRole::Admin]);
        $membership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);

        config(['diddyvisor.mcp.user' => $user->email]);

        DiddyVisorServer::tool(ListMembersTool::class, ['house_id' => $house->id])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('data.total', 2)
                ->where('data.items.0.id', $house->memberships()->where('user_id', $user->id)->sole()->id)
                ->where('data.items.0.name', 'Ana')
                ->where('data.items.0.role', 'admin')
                ->where('data.items.1.id', $membership->id)
                ->where('data.items.1.name', 'Bia')
                ->where('data.items.1.role', 'member')
                ->etc());
    }
}
