<?php

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

uses(RefreshDatabase::class);

test('admin marks and unmarks a share of another member', function () {
    [$user, $house, , $memberMembership, $bill] = setSharePaymentHouseWithBill();

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
});

test('member marks their own share', function () {
    [, $house, , $memberMembership, $bill] = setSharePaymentHouseWithBill();
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
});

test('member cannot mark someone elses share', function () {
    [, $house, $adminMembership, $memberMembership, $bill] = setSharePaymentHouseWithBill();
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
});

/**
 * @return array{0: User, 1: House, 2: Membership, 3: Membership, 4: Bill}
 */
function setSharePaymentHouseWithBill(): array
{
    $user = User::factory()->create(['name' => 'Ana']);
    $member = User::factory()->create(['name' => 'Bia']);
    $house = House::create(['name' => 'Casa A']);
    $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => 'Ana', 'role' => MembershipRole::Admin]);
    $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);
    $bill = app(SaveBill::class)($user, $house, '2026-10', ['name' => 'Aluguel', 'due_date' => '2026-10-20', 'total' => '100,00', 'participants' => [$adminMembership->id, $memberMembership->id], 'shares' => []]);

    return [$user, $house, $adminMembership, $memberMembership, $bill];
}
