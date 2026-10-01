<?php

use App\Actions\Bills\DeleteBill;
use App\Actions\Bills\SaveBill;
use App\Actions\Bills\SetSharePayment;
use App\Actions\Houses\ManageMembership;
use App\Enums\MembershipRole;
use App\Filament\Pages\MonthlyBills;
use App\Models\House;
use App\Models\Membership;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('invalid sum rejects without creating bill', function () {
    $this->withoutVite();
    [$user, $house, $member] = billIntegrityHouse();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    Livewire::test(MonthlyBills::class)->callAction('createBill', data: [...billIntegrityInput($member->id), 'shares' => [['membership_id' => $member->id, 'amount' => '9,99']]])->assertHasFormErrors(['shares']);
    expect($house->bills()->get())->toHaveCount(0);
});

test('participant from other house rejects at public operation', function () {
    [$user, $house] = billIntegrityHouse();
    [, , $otherMember] = billIntegrityHouse();
    app(SaveBill::class)($user, $house, now()->format('Y-m'), billIntegrityInput($otherMember->id));
})->throws(ValidationException::class);

test('any payment blocks delete even when called directly', function () {
    [$user, $house, $member] = billIntegrityHouse();
    $bill = app(SaveBill::class)($user, $house, now()->format('Y-m'), billIntegrityInput($member->id));
    app(SetSharePayment::class)($user, $house, $bill->id, $member->id, true);
    app(DeleteBill::class)($user, $house, $bill->id);
})->throws(ValidationException::class);

test('removed members part survives name edit and reentry creates new association', function () {
    $this->withoutVite();
    [$admin, $house] = billIntegrityHouse();
    $user = User::factory()->create(['name' => 'Bruno']);
    $old = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name]);
    $input = billIntegrityInput($old->id);
    $bill = app(SaveBill::class)($admin, $house, now()->format('Y-m'), $input);
    app(ManageMembership::class)->remove($admin, $house, $old->id);
    $new = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name]);
    expect($new->id)->not->toBe($old->id);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    Livewire::test(MonthlyBills::class)->callAction(TestAction::make('editBill')->table($bill), data: [...$input, 'name' => 'Nome atualizado', 'shares' => [['membership_id' => $old->id, 'amount' => '10,00']]])->assertHasNoFormErrors()->assertSee('Nome atualizado')->assertSee('Bruno · Removido');
    $this->actingAs($user);
    Livewire::test(MonthlyBills::class)->call('updateTableColumnState', 'member_'.$old->id.'_paid', (string) $bill->id, true)->assertSee('Pendente');
    expect($bill->shares()->sole()->is_paid)->toBeFalse();
});

/**
 * @return array{0: User, 1: House, 2: Membership}
 */
function billIntegrityHouse(): array
{
    $user = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $member = $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => MembershipRole::Admin]);

    return [$user, $house, $member];
}

/**
 * @return array<string, mixed>
 */
function billIntegrityInput(int $membershipId): array
{
    return ['name' => 'Conta', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => [$membershipId], 'shares' => []];
}
