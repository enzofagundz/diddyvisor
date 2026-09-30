<?php

namespace Tests\Feature;

use App\Actions\Invitations\SendInvitation;
use App\Enums\MembershipRole;
use App\Filament\Pages\HouseMembers;
use App\Models\House;
use App\Models\User;
use App\Notifications\HouseInvitation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_does_not_prefill_email_from_invalid_or_expired_invitation(): void
    {
        $this->withoutVite();
        Notification::fake();
        $admin = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
        $invitation = app(SendInvitation::class)($admin, $house, 'invited@example.com');
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->withSession(['pending_invitation' => ['id' => $invitation->id, 'hash' => str_repeat('0', 64)]]);
        Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->assertFormSet(['email' => null]);
        $this->withSession(['pending_invitation' => ['id' => $invitation->id, 'hash' => $invitation->token_hash]]);
        $this->travel(7)->days();
        Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->assertFormSet(['email' => null]);
    }

    public function test_invitation_rejects_wrong_email_unverified_user_and_expired_token(): void
    {
        $this->withoutVite();
        Notification::fake();
        $admin = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
        $invitation = app(SendInvitation::class)($admin, $house, 'invited@example.com');
        $url = '';
        Notification::assertSentOnDemand(HouseInvitation::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->post('/convites/'.$invitation->id.'/preparar', ['token' => str_repeat('0', 64)])->assertForbidden();
        $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $fragment['token']])->assertRedirect();
        $wrong = User::factory()->create();
        $this->actingAs($wrong)->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
        $invited = User::factory()->unverified()->create(['email' => 'invited@example.com']);
        $this->actingAs($invited)->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
        $invited->markEmailAsVerified();
        $this->travel(7)->days();
        $this->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
        $this->assertCount(1, $house->memberships()->get());
    }

    public function test_invitation_survives_registration_and_email_verification(): void
    {
        $this->withoutVite();
        Notification::fake();
        $admin = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
        $invitation = app(SendInvitation::class)($admin, $house, 'new@example.com');
        $url = '';
        Notification::assertSentOnDemand(HouseInvitation::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $fragment['token']])->assertRedirect(Filament::getPanel('app')->getRegistrationUrl());
        $this->get('/convites/'.$invitation->id)->assertRedirect(Filament::getPanel('app')->getRegistrationUrl());
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->assertFormSet(['email' => 'new@example.com'])->fillForm([
            'name' => 'Novo morador', 'password' => 'valid-password-123', 'passwordConfirmation' => 'valid-password-123',
        ])->call('register')->assertHasNoFormErrors()->assertRedirect(url('/convites/'.$invitation->id));
        $user = auth()->user();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->get('/convites/'.$invitation->id)->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());
        $verificationUrl = URL::temporarySignedRoute('filament.app.auth.email-verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($verificationUrl)->assertRedirect(url('/convites/'.$invitation->id));
        $this->post('/convites/'.$invitation->id.'/aceitar')->assertRedirect();
        $this->assertTrue($user->fresh()->canAccessTenant($house));
    }

    public function test_resend_replaces_token_and_restarts_seven_day_expiry(): void
    {
        $this->withoutVite();
        Notification::fake();
        $admin = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
        $invitation = app(SendInvitation::class)($admin, $house, 'bruno@example.com');
        $url = '';
        Notification::assertSentOnDemand(HouseInvitation::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(HouseMembers::class)->callAction('resendInvitation', arguments: ['id' => $invitation->id])->assertHasNoErrors();
        Notification::assertSentOnDemandTimes(HouseInvitation::class, 2);
        $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $fragment['token']])->assertForbidden();
    }

    public function test_admin_revokes_invitation_from_members_page(): void
    {
        $this->withoutVite();
        Notification::fake();
        $admin = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
        $invitation = app(SendInvitation::class)($admin, $house, 'bruno@example.com');
        $url = '';
        Notification::assertSentOnDemand(HouseInvitation::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(HouseMembers::class)->callAction('revokeInvitation', arguments: ['id' => $invitation->id])->assertDontSee('bruno@example.com');
        $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $fragment['token']])->assertForbidden();
    }

    public function test_verified_invitee_without_house_accepts_once_through_http(): void
    {
        $this->withoutVite();
        Notification::fake();
        $admin = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'bruno@example.com']);
        $house = House::create(['name' => 'Casa do Bruno']);
        $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
        $invitation = app(SendInvitation::class)($admin, $house, $invitee->email);
        $url = '';
        Notification::assertSentOnDemand(HouseInvitation::class, function ($notification) use (&$url) {
            $url = $notification->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);
        $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $fragment['token']])->assertRedirect(Filament::getPanel('app')->getRegistrationUrl());
        $this->actingAs($invitee)->get('/convites/'.$invitation->id)->assertOk()->assertSee('Casa do Bruno');
        $this->post('/convites/'.$invitation->id.'/aceitar')->assertRedirect();
        $this->assertTrue($invitee->canAccessTenant($house));
        $this->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
    }

    public function test_admin_sends_invitation_to_email_from_members_page(): void
    {
        $this->withoutVite();
        Notification::fake();
        $admin = User::factory()->create();
        $house = House::create(['name' => 'Casa']);
        $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($house);
        Livewire::test(HouseMembers::class)->callAction('inviteMember', data: ['email' => 'bruno@example.com'])->assertHasNoFormErrors()->assertSee('bruno@example.com');
        Notification::assertSentOnDemand(HouseInvitation::class, fn ($notification, $channels, $recipient) => $recipient->routes['mail'] === 'bruno@example.com');
    }
}
