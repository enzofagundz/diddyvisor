<?php

namespace Tests\Feature;

use App\Actions\Bills\DeleteBill;
use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Actions\Houses\ManageMembership;
use App\Enums\MembershipRole;
use App\Filament\Pages\MonthlyBills;
use App\Models\House;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BillIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_sum_rejects_without_creating_bill(): void
    {
        $this->withoutVite();
        [$user, $house, $member] = $this->house();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(MonthlyBills::class)->callAction('createBill', data: [...$this->input($member->id), 'shares' => [['membership_id' => $member->id, 'amount' => '9,99']]])->assertHasFormErrors(['shares']);
        $this->assertCount(0, $house->bills()->get());
    }

    public function test_participant_from_other_house_rejects_at_public_operation(): void
    {
        [$user, $house] = $this->house();
        [$other, $otherHouse, $otherMember] = $this->house();
        $this->expectException(ValidationException::class);
        app(SaveBill::class)($user, $house, now()->format('Y-m'), $this->input($otherMember->id));
    }

    public function test_any_payment_blocks_delete_even_when_called_directly(): void
    {
        [$user, $house, $member] = $this->house();
        $bill = app(SaveBill::class)($user, $house, now()->format('Y-m'), $this->input($member->id));
        app(SetSharePayment::class)($user, $house, $bill->id, $member->id, true);
        $this->expectException(ValidationException::class);
        app(DeleteBill::class)($user, $house, $bill->id);
    }

    public function test_removed_members_part_survives_name_edit_and_reentry_creates_new_association(): void
    {
        $this->withoutVite();
        [$admin, $house, $adminMember] = $this->house();
        $user = User::factory()->create(['name' => 'Bruno']);
        $old = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name]);
        $input = $this->input($old->id);
        $bill = app(SaveBill::class)($admin, $house, now()->format('Y-m'), $input);
        app(ManageMembership::class)->remove($admin, $house, $old->id);
        $new = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name]);
        $this->assertNotSame($old->id, $new->id);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(MonthlyBills::class)->callAction(TestAction::make('editBill')->table($bill), data: [...$input, 'name' => 'Nome atualizado', 'shares' => [['membership_id' => $old->id, 'amount' => '10,00']]])->assertHasNoFormErrors()->assertSee('Nome atualizado')->assertSee('Bruno · Removido');
        $this->actingAs($user);
        Livewire::test(MonthlyBills::class)->call('updateTableColumnState', 'member_'.$old->id.'_paid', (string) $bill->id, true)->assertSee('Pendente');
        $this->assertFalse($bill->shares()->sole()->is_paid);
    }

    private function house(): array
    {
        $user = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);

        return [$user, $house, $member];
    }

    private function input(int $membershipId): array
    {
        return ['name' => 'Conta', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => [$membershipId], 'shares' => []];
    }
}
