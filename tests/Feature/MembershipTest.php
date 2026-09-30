<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Filament\Pages\HouseMembers;
use App\Models\House;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_admin_must_promote_another_member_before_leaving(): void
    {
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
        $this->assertTrue($admin->canAccessTenant($house));
        $page->call('unmountAction');
        $page->callAction(TestAction::make('promoteMember')->table($membership))->assertHasNoErrors();
        $this->assertTrue($house->isAdmin($other));
        $page->callAction('leaveHouse')->assertRedirect();
        $this->assertFalse($admin->canAccessTenant($house));
    }

    public function test_admin_removes_member_preserving_name_and_revoking_access(): void
    {
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
        $this->assertFalse($member->canAccessTenant($house));
        $member->update(['name' => 'Nome alterado']);
        $this->assertStringContainsString('Bruno', $membership->fresh()->label());
    }
}
