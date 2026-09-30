<?php

namespace Tests\Feature;

use App\Actions\Bills\SaveBill;
use App\Actions\Houses\ManageMembership;
use App\Enums\MembershipRole;
use App\Filament\Pages\MonthlyBills;
use App\Models\House;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_changes_only_own_payment_even_with_forged_column_calls(): void
    {
        $this->withoutVite();
        [$admin, $member, $house, $memberships, $bill] = $this->houseWithBill();
        $this->actingAs($member);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class)->assertActionHidden('createBill');
        $page->call('updateTableColumnState', 'member_'.$memberships[0]->id.'_paid', (string) $bill->id, true)->assertSee('Pendente');
        $page->call('updateTableColumnState', 'member_'.$memberships[1]->id.'_paid', (string) $bill->id, true)->assertSee('Parcial');
        $this->assertFalse($bill->shares()->where('membership_id', $memberships[0]->id)->sole()->is_paid);
        $this->assertTrue($bill->shares()->where('membership_id', $memberships[1]->id)->sole()->is_paid);
    }

    public function test_member_cannot_call_public_financial_operation(): void
    {
        [$admin, $member, $house, $memberships] = $this->houseWithBill();
        $this->expectException(AuthorizationException::class);
        app(SaveBill::class)($member, $house, now()->format('Y-m'), ['name' => 'Adulterada', 'due_date' => now()->toDateString(), 'total' => '1,00', 'participants' => [$memberships[1]->id], 'shares' => []]);
    }

    public function test_another_house_cannot_be_read_or_changed(): void
    {
        $this->withoutVite();
        [$admin, $member, $house] = $this->houseWithBill();
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        $this->get(MonthlyBills::getUrl(panel: 'app', tenant: $house))->assertNotFound();
    }

    public function test_removal_revokes_open_livewire_session_before_next_payment(): void
    {
        $this->withoutVite();
        [$admin, $member, $house, $memberships, $bill] = $this->houseWithBill();
        $this->actingAs($member);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        $page = Livewire::test(MonthlyBills::class);
        app(ManageMembership::class)->remove($admin, $house, $memberships[1]->id);
        $page->call('updateTableColumnState', 'member_'.$memberships[1]->id.'_paid', (string) $bill->id, true)->assertForbidden();
        $this->assertFalse($bill->shares()->where('membership_id', $memberships[1]->id)->sole()->is_paid);
    }

    public function test_unverified_user_cannot_access_house_or_create_house(): void
    {
        [$admin, $member, $house] = $this->houseWithBill();
        $member->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse($member->canAccessTenant($house));
        $this->actingAs($member);
        $this->get(MonthlyBills::getUrl(panel: 'app', tenant: $house))->assertNotFound();
        $this->assertFalse($member->can('create', House::class));
    }

    private function houseWithBill(): array
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $memberships = collect([$admin, $member])->map(fn ($user) => $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => $user->is($admin) ? MembershipRole::Admin : MembershipRole::Member]));
        $bill = app(SaveBill::class)($admin, $house, now()->format('Y-m'), ['name' => 'Conta', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => $memberships->pluck('id')->all(), 'shares' => []]);

        return [$admin, $member, $house, $memberships, $bill];
    }
}
