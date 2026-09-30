<?php

namespace Tests\Feature;

use App\Filament\Pages\Tenancy\EditHouse;
use App\Filament\Pages\Tenancy\RegisterHouse;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HouseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_deletes_empty_house_without_deleting_user(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(RegisterHouse::class)->fillForm(['name' => 'Casa vazia'])->call('register');
        Filament::setTenant($user->houses()->sole());
        Livewire::test(EditHouse::class)->callAction('deleteHouse')->assertRedirect();
        $this->assertCount(0, $user->getTenants(Filament::getPanel('app')));
        $this->assertNotNull($user->fresh());
    }

    public function test_admin_renames_house_through_profile(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(RegisterHouse::class)->fillForm(['name' => 'Casa antiga'])->call('register');
        $house = $user->houses()->sole();
        Filament::setTenant($house);
        Livewire::test(EditHouse::class)->fillForm(['name' => 'Casa nova'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Casa nova', $user->houses()->sole()->name);
    }

    public function test_verified_user_creates_house_and_becomes_admin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        Livewire::test(RegisterHouse::class)
            ->fillForm(['name' => 'Casa compartilhada'])
            ->call('register')
            ->assertHasNoFormErrors();

        $house = $user->houses()->sole();
        $this->assertSame('Casa compartilhada', $house->name);
        $this->assertTrue($house->isAdmin($user));
        $this->assertTrue($user->canAccessTenant($house));
    }
}
