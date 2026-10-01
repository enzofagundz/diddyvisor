<?php

use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Enums\MembershipRole;
use App\Mcp\Servers\DiddyVisorServer;
use App\Mcp\Tools\Bills\DeleteBillTool;
use App\Mcp\Tools\Bills\GetBillTool;
use App\Models\Bill;
use App\Models\House;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(RefreshDatabase::class);

test('admin deletes a bill without payments', function () {
    [$user, $house, $bill] = deleteBillHouseWithBill();

    config(['diddyvisor.mcp.user' => $user->email]);

    DiddyVisorServer::tool(DeleteBillTool::class, [
        'house_id' => $house->id,
        'bill_id' => $bill->id,
        'confirm' => true,
    ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('ok', true)
            ->where('data.deleted', true)
            ->where('data.id', $bill->id)
            ->etc());

    DiddyVisorServer::tool(GetBillTool::class, ['house_id' => $house->id, 'bill_id' => $bill->id])
        ->assertHasErrors()
        ->assertSee('"code":"not_found"');
});

test('requires confirm', function () {
    [$user, $house, $bill] = deleteBillHouseWithBill();

    config(['diddyvisor.mcp.user' => $user->email]);

    DiddyVisorServer::tool(DeleteBillTool::class, [
        'house_id' => $house->id,
        'bill_id' => $bill->id,
    ])
        ->assertHasErrors()
        ->assertSee('"code":"conflict"')
        ->assertSee('confirm=true');

    DiddyVisorServer::tool(GetBillTool::class, ['house_id' => $house->id, 'bill_id' => $bill->id])
        ->assertOk();
});

test('blocks deletion when a share is paid', function () {
    [$user, $house, $bill, $memberMembership] = deleteBillHouseWithBill();
    app(SetSharePayment::class)($user, $house, $bill->id, $memberMembership->id, true);

    config(['diddyvisor.mcp.user' => $user->email]);

    DiddyVisorServer::tool(DeleteBillTool::class, [
        'house_id' => $house->id,
        'bill_id' => $bill->id,
        'confirm' => true,
    ])
        ->assertHasErrors()
        ->assertSee('"code":"validation"')
        ->assertSee('Conta com pagamentos não pode ser excluída.');

    DiddyVisorServer::tool(GetBillTool::class, ['house_id' => $house->id, 'bill_id' => $bill->id])
        ->assertOk();
});

/**
 * @return array{0: User, 1: House, 2: Bill, 3: Membership}
 */
function deleteBillHouseWithBill(): array
{
    $user = User::factory()->create(['name' => 'Ana']);
    $member = User::factory()->create(['name' => 'Bia']);
    $house = House::create(['name' => 'Casa A']);
    $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => 'Ana', 'role' => MembershipRole::Admin]);
    $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);
    $bill = app(SaveBill::class)($user, $house, '2026-10', ['name' => 'Aluguel', 'due_date' => '2026-10-20', 'total' => '100,00', 'participants' => [$adminMembership->id, $memberMembership->id], 'shares' => []]);

    return [$user, $house, $bill, $memberMembership];
}
