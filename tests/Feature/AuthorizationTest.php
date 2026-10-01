<?php

use App\Actions\Bills\SaveBill;
use App\Actions\Houses\ManageMembership;
use App\Enums\MembershipRole;
use App\Filament\Pages\MonthlyBills;
use App\Models\Bill;
use App\Models\House;
use App\Models\Membership;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('member changes only own payment even with forged column calls', function () {
    $this->withoutVite();
    [, $member, $house, $memberships, $bill] = authorizationHouseWithBill();
    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    $page = Livewire::test(MonthlyBills::class)->assertActionHidden('createBill');
    $page->call('updateTableColumnState', 'member_'.$memberships[0]->id.'_paid', (string) $bill->id, true)->assertSee('Pendente');
    $page->call('updateTableColumnState', 'member_'.$memberships[1]->id.'_paid', (string) $bill->id, true)->assertSee('Parcial');
    expect($bill->shares()->where('membership_id', $memberships[0]->id)->sole()->is_paid)->toBeFalse()
        ->and($bill->shares()->where('membership_id', $memberships[1]->id)->sole()->is_paid)->toBeTrue();
});

test('member cannot call public financial operation', function () {
    [, $member, $house, $memberships] = authorizationHouseWithBill();
    app(SaveBill::class)($member, $house, now()->format('Y-m'), ['name' => 'Adulterada', 'due_date' => now()->toDateString(), 'total' => '1,00', 'participants' => [$memberships[1]->id], 'shares' => []]);
})->throws(AuthorizationException::class);

test('another house cannot be read or changed', function () {
    $this->withoutVite();
    [, , $house] = authorizationHouseWithBill();
    $outsider = User::factory()->create();
    $this->actingAs($outsider);
    $this->get(MonthlyBills::getUrl(panel: 'app', tenant: $house))->assertNotFound();
});

test('removal revokes open livewire session before next payment', function () {
    $this->withoutVite();
    [$admin, $member, $house, $memberships, $bill] = authorizationHouseWithBill();
    $this->actingAs($member);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    $page = Livewire::test(MonthlyBills::class);
    app(ManageMembership::class)->remove($admin, $house, $memberships[1]->id);
    $page->call('updateTableColumnState', 'member_'.$memberships[1]->id.'_paid', (string) $bill->id, true)->assertForbidden();
    expect($bill->shares()->where('membership_id', $memberships[1]->id)->sole()->is_paid)->toBeFalse();
});

test('unverified user cannot access house or create house', function () {
    [, $member, $house] = authorizationHouseWithBill();
    $member->forceFill(['email_verified_at' => null])->save();
    expect($member->canAccessTenant($house))->toBeFalse();
    $this->actingAs($member);
    $this->get(MonthlyBills::getUrl(panel: 'app', tenant: $house))->assertNotFound();
    expect($member->can('create', House::class))->toBeFalse();
});

/**
 * @return array{0: User, 1: User, 2: House, 3: Collection<int, Membership>, 4: Bill}
 */
function authorizationHouseWithBill(): array
{
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $memberships = collect([$admin, $member])->map(fn ($user) => $house->memberships()->create(['user_id' => $user->id, 'display_name' => $user->name, 'role' => $user->is($admin) ? MembershipRole::Admin : MembershipRole::Member]));
    $bill = app(SaveBill::class)($admin, $house, now()->format('Y-m'), ['name' => 'Conta', 'due_date' => now()->toDateString(), 'total' => '10,00', 'participants' => $memberships->pluck('id')->all(), 'shares' => []]);

    return [$admin, $member, $house, $memberships, $bill];
}
