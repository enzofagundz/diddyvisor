<?php

use App\Filament\Pages\Tenancy\EditHouse;
use App\Filament\Pages\Tenancy\RegisterHouse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin deletes empty house without deleting user', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::test(RegisterHouse::class)->fillForm(['name' => 'Casa vazia'])->call('register');
    Filament::setTenant($user->houses()->sole());
    Livewire::test(EditHouse::class)->callAction('deleteHouse')->assertRedirect();
    expect($user->getTenants(Filament::getPanel('app')))->toHaveCount(0)
        ->and($user->fresh())->not->toBeNull();
});

test('admin renames house through profile', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::test(RegisterHouse::class)->fillForm(['name' => 'Casa antiga'])->call('register');
    $house = $user->houses()->sole();
    Filament::setTenant($house);
    Livewire::test(EditHouse::class)->fillForm(['name' => 'Casa nova'])->call('save')->assertHasNoFormErrors();
    expect($user->houses()->sole()->name)->toBe('Casa nova');
});

test('verified user creates house and becomes admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('app'));

    Livewire::test(RegisterHouse::class)
        ->fillForm(['name' => 'Casa compartilhada'])
        ->call('register')
        ->assertHasNoFormErrors();

    $house = $user->houses()->sole();
    expect($house->name)->toBe('Casa compartilhada')
        ->and($house->isAdmin($user))->toBeTrue()
        ->and($user->canAccessTenant($house))->toBeTrue();
});
