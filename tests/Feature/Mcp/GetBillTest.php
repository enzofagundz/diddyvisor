<?php

namespace Tests\Feature\Mcp;

use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Bills\GetBillTool;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class GetBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_bill_with_shares_and_payment_status(): void
    {
        $user = User::factory()->create();
        $member = User::factory()->create(['name' => 'Bia']);
        $house = House::create(['name' => 'Casa A']);
        $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);
        $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);
        $bill = app(SaveBill::class)($user, $house, '2026-10', ['name' => 'Aluguel', 'due_date' => '2026-10-20', 'total' => '100,00', 'participants' => [$adminMembership->id, $memberMembership->id], 'shares' => []]);
        app(SetSharePayment::class)($user, $house, $bill->id, $memberMembership->id, true);

        config(['diddyvisor.mcp.user' => $user->email]);

        DiddyVisorServer::tool(GetBillTool::class, ['house_id' => $house->id, 'bill_id' => $bill->id])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('data.bill.id', $bill->id)
                ->where('data.bill.status', 'partial')
                ->where('data.bill.total', '100,00')
                ->where('data.bill.shares.0.membership_id', $adminMembership->id)
                ->where('data.bill.shares.0.amount', '50,00')
                ->where('data.bill.shares.0.is_paid', false)
                ->where('data.bill.shares.1.membership_id', $memberMembership->id)
                ->where('data.bill.shares.1.amount', '50,00')
                ->where('data.bill.shares.1.is_paid', true)
                ->etc());
    }
}
