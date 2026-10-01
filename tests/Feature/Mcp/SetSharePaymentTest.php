<?php

namespace Tests\Feature\Mcp;

use App\Actions\Bills\SaveBill;
use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Bills\GetBillTool;
use App\Mcp\Tools\Bills\SetSharePaymentTool;
use App\Models\Bill;
use App\Models\House;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class SetSharePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_marks_and_unmarks_a_share_of_another_member(): void
    {
        [$user, $house, $adminMembership, $memberMembership, $bill] = $this->houseWithBill();

        config(['diddyvisor.mcp.user' => $user->email]);

        DiddyVisorServer::tool(SetSharePaymentTool::class, [
            'house_id' => $house->id,
            'bill_id' => $bill->id,
            'membership_id' => $memberMembership->id,
            'paid' => true,
        ])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('ok', true)
                ->where('data.bill.status', 'partial')
                ->where('data.bill.shares.0.is_paid', false)
                ->where('data.bill.shares.1.is_paid', true)
                ->etc());

        DiddyVisorServer::tool(SetSharePaymentTool::class, [
            'house_id' => $house->id,
            'bill_id' => $bill->id,
            'membership_id' => $memberMembership->id,
            'paid' => false,
        ])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('data.bill.status', 'pending')
                ->where('data.bill.shares.1.is_paid', false)
                ->etc());
    }

    public function test_member_marks_their_own_share(): void
    {
        [$user, $house, $adminMembership, $memberMembership, $bill] = $this->houseWithBill();
        $member = $memberMembership->user;

        config(['diddyvisor.mcp.user' => $member->email]);

        DiddyVisorServer::tool(SetSharePaymentTool::class, [
            'house_id' => $house->id,
            'bill_id' => $bill->id,
            'membership_id' => $memberMembership->id,
            'paid' => true,
        ])
            ->assertOk()
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('data.bill.shares.1.is_paid', true)
                ->etc());
    }

    public function test_member_cannot_mark_someone_elses_share(): void
    {
        [, $house, $adminMembership, $memberMembership, $bill] = $this->houseWithBill();
        $member = $memberMembership->user;

        config(['diddyvisor.mcp.user' => $member->email]);

        DiddyVisorServer::tool(SetSharePaymentTool::class, [
            'house_id' => $house->id,
            'bill_id' => $bill->id,
            'membership_id' => $adminMembership->id,
            'paid' => true,
        ])
            ->assertHasErrors()
            ->assertSee('"code":"forbidden"');

        DiddyVisorServer::tool(GetBillTool::class, ['house_id' => $house->id, 'bill_id' => $bill->id])
            ->assertStructuredContent(fn (AssertableJson $json) => $json
                ->where('data.bill.shares.0.is_paid', false)
                ->etc());
    }

    /**
     * @return array{0: User, 1: House, 2: Membership, 3: Membership, 4: Bill}
     */
    private function houseWithBill(): array
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $member = User::factory()->create(['name' => 'Bia']);
        $house = House::create(['name' => 'Casa A']);
        $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => 'Ana', 'role' => MembershipRole::Admin]);
        $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);
        $bill = app(SaveBill::class)($user, $house, '2026-10', ['name' => 'Aluguel', 'due_date' => '2026-10-20', 'total' => '100,00', 'participants' => [$adminMembership->id, $memberMembership->id], 'shares' => []]);

        return [$user, $house, $adminMembership, $memberMembership, $bill];
    }
}
