<?php

namespace Tests\Feature\Mcp;

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
use Tests\TestCase;

class DeleteBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_deletes_a_bill_without_payments(): void
    {
        [$user, $house, $bill] = $this->houseWithBill();

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
    }

    public function test_requires_confirm(): void
    {
        [$user, $house, $bill] = $this->houseWithBill();

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
    }

    public function test_blocks_deletion_when_a_share_is_paid(): void
    {
        [$user, $house, $bill, $memberMembership] = $this->houseWithBill();
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
    }

    /**
     * @return array{0: User, 1: House, 2: Bill, 3: Membership}
     */
    private function houseWithBill(): array
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $member = User::factory()->create(['name' => 'Bia']);
        $house = House::create(['name' => 'Casa A']);
        $adminMembership = $house->memberships()->create(['user_id' => $user->id, 'display_name' => 'Ana', 'role' => MembershipRole::Admin]);
        $memberMembership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bia', 'role' => MembershipRole::Member]);
        $bill = app(SaveBill::class)($user, $house, '2026-10', ['name' => 'Aluguel', 'due_date' => '2026-10-20', 'total' => '100,00', 'participants' => [$adminMembership->id, $memberMembership->id], 'shares' => []]);

        return [$user, $house, $bill, $memberMembership];
    }
}
