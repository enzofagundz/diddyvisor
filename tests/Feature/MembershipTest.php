<?php

use App\Enums\MembershipRole;
use App\Filament\Pages\HouseMembers;
use App\Models\House;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('last admin must promote another member before leaving', function () {
    $this->withoutVite();
    $admin = User::factory()->create();
    $other = User::factory()->create();
    $house = House::create(['name' => 'Casa']);
    $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
    $membership = $house->memberships()->create(['user_id' => $other->id, 'display_name' => $other->name]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    $page = Livewire::test(HouseMembers::class)->callAction('leaveHouse')->assertHasErrors(['membership']);
    expect($admin->canAccessTenant($house))->toBeTrue();
    $page->call('unmountAction');
    $page->callAction(TestAction::make('promoteMember')->table($membership))->assertHasNoErrors();
    expect($house->isAdmin($other))->toBeTrue();
    $page->callAction('leaveHouse')->assertRedirect();
    expect($admin->canAccessTenant($house))->toBeFalse();
});

test('admin removes member preserving name and revoking access', function () {
    $this->withoutVite();
    $admin = User::factory()->create();
    $member = User::factory()->create(['name' => 'Bruno']);
    $house = House::create(['name' => 'Casa']);
    $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
    $membership = $house->memberships()->create(['user_id' => $member->id, 'display_name' => 'Bruno']);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    Livewire::test(HouseMembers::class)->callAction(TestAction::make('removeMember')->table($membership))->assertSee('Bruno · Removido');
    expect($member->canAccessTenant($house))->toBeFalse();
    $member->update(['name' => 'Nome alterado']);
    expect($membership->fresh()->label())->toContain('Bruno');
});
