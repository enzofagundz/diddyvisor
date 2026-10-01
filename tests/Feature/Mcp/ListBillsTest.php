<?php

namespace Tests\Feature\Mcp;

use App\Actions\Bills\SaveBill;
use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Bills\ListBillsTool;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class ListBillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_bills_of_a_month_for_a_house(): void
    {
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa A']);
        $membership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $save = app(SaveBill::class);
        $october = $save($user, $house, '2026-10', ['name' => 'Aluguel', 'due_date' => '2026-10-20', 'total' => '100,00', 'participants' => [$membership->id], 'shares' => []]);
        $save($user, $house, '2026-11', ['name' => 'Luz', 'due_date' => '2026-11-05', 'total' => '80,00', 'participants' => [$membership->id], 'shares' => []]);

        config(['diddyvisor.mcp.user' => $user->email]);

        DiddyVisorServer::tool(ListBillsTool::class, ['house_id' => $house->id, 'month' => '2026-10'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('data.total', 1)
                ->where('data.next_offset', null)
                ->where('data.items.0.id', $october->id)
                ->where('data.items.0.name', 'Aluguel')
                ->where('data.items.0.month', '2026-10')
                ->where('data.items.0.due_date', '2026-10-20')
                ->where('data.items.0.total', '100,00')
                ->where('data.items.0.total_cents', 10000)
                ->where('data.items.0.status', 'pending')
                ->where('data.items.0.overdue', false)
                ->etc());
    }
}
