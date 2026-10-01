<?php

namespace Tests\Feature\Mcp;

use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Bills\GetMonthlySummaryTool;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class GetMonthlySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_month_totals_and_per_member_balances(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $member = User::factory()->create(['name' => 'Bia']);
        $house = House::create(['name' => 'Casa A']);
        $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => 'Ana', 'role' => MembershipRole::Admin]);
        $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);
        $bill = app(SaveBill::class)($user, $house, '2026-10', ['name' => 'Aluguel', 'due_date' => '2026-10-20', 'total' => '100,00', 'participants' => [$adminMembership->id, $memberMembership->id], 'shares' => []]);
        app(SetSharePayment::class)($user, $house, $bill->id, $memberMembership->id, true);

        config(['diddyvisor.mcp.user' => $user->email]);

        DiddyVisorServer::tool(GetMonthlySummaryTool::class, ['house_id' => $house->id, 'month' => '2026-10'])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('data.month', '2026-10')
                ->where('data.count', 1)
                ->where('data.total', '100,00')
                ->where('data.total_cents', 10000)
                ->where('data.paid_bills', 0)
                ->where('data.pending_bills', 1)
                ->where('data.paid', '50,00')
                ->where('data.pending', '50,00')
                ->where('data.members.0.membership_id', $adminMembership->id)
                ->where('data.members.0.name', 'Ana #'.$adminMembership->id)
                ->where('data.members.0.paid', '0,00')
                ->where('data.members.0.pending', '50,00')
                ->where('data.members.1.membership_id', $memberMembership->id)
                ->where('data.members.1.name', 'Bia #'.$memberMembership->id)
                ->where('data.members.1.paid', '50,00')
                ->where('data.members.1.pending', '0,00')
                ->where('data.upcoming.0.id', $bill->id)
                ->etc());
    }
}
