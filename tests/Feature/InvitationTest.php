<?php

use App\Actions\Invitations\SendInvitation;
use App\Enums\MembershipRole;
use App\Filament\Pages\HouseMembers;
use App\Models\House;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\HouseInvitation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('registration does not prefill email from invalid or expired invitation', function () {
    $this->withoutVite();
    [, , $invitation] = invitationScenario('invited@example.com');
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->withSession(['pending_invitation' => ['id' => $invitation->id, 'hash' => str_repeat('0', 64)]]);
    Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->assertFormSet(['email' => null]);
    $this->withSession(['pending_invitation' => ['id' => $invitation->id, 'hash' => $invitation->token_hash]]);
    $this->travel(7)->days();
    Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->assertFormSet(['email' => null]);
});

test('invitation rejects wrong email unverified user and expired token', function () {
    $this->withoutVite();
    [, $house, $invitation, $token] = invitationScenario('invited@example.com');
    $this->post('/convites/'.$invitation->id.'/preparar', ['token' => str_repeat('0', 64)])->assertForbidden();
    $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $token])->assertRedirect();
    $wrong = User::factory()->create();
    $this->actingAs($wrong)->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
    $invited = User::factory()->unverified()->create(['email' => 'invited@example.com']);
    $this->actingAs($invited)->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
    $invited->markEmailAsVerified();
    $this->travel(7)->days();
    $this->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
    expect($house->memberships()->get())->toHaveCount(1);
});

test('invitation survives registration and email verification', function () {
    $this->withoutVite();
    [, $house, $invitation, $token] = invitationScenario('new@example.com');
    $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $token])->assertRedirect(Filament::getPanel('app')->getRegistrationUrl());
    $this->get('/convites/'.$invitation->id)->assertRedirect(Filament::getPanel('app')->getRegistrationUrl());
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->assertFormSet(['email' => 'new@example.com'])->fillForm([
        'name' => 'Novo morador', 'password' => 'valid-password-123', 'passwordConfirmation' => 'valid-password-123',
    ])->call('register')->assertHasNoFormErrors()->assertRedirect(url('/convites/'.$invitation->id));
    $user = auth()->user();
    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->get('/convites/'.$invitation->id)->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());
    $verificationUrl = URL::temporarySignedRoute('filament.app.auth.email-verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->get($verificationUrl)->assertRedirect(url('/convites/'.$invitation->id));
    $this->post('/convites/'.$invitation->id.'/aceitar')->assertRedirect();
    expect($user->fresh()->canAccessTenant($house))->toBeTrue();
});

test('resend replaces token and restarts seven day expiry', function () {
    $this->withoutVite();
    [$admin, $house, $invitation, $token] = invitationScenario('bruno@example.com');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    Livewire::test(HouseMembers::class)->callAction('resendInvitation', arguments: ['id' => $invitation->id])->assertHasNoErrors();
    Notification::assertSentOnDemandTimes(HouseInvitation::class, 2);
    $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $token])->assertForbidden();
});

test('admin revokes invitation from members page', function () {
    $this->withoutVite();
    [$admin, $house, $invitation, $token] = invitationScenario('bruno@example.com');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    Livewire::test(HouseMembers::class)->callAction('revokeInvitation', arguments: ['id' => $invitation->id])->assertDontSee('bruno@example.com');
    $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $token])->assertForbidden();
});

test('verified invitee without house accepts once through http', function () {
    $this->withoutVite();
    $invitee = User::factory()->create(['email' => 'bruno@example.com']);
    [, $house, $invitation, $token] = invitationScenario($invitee->email, 'Casa do Bruno');
    $this->post('/convites/'.$invitation->id.'/preparar', ['token' => $token])->assertRedirect(Filament::getPanel('app')->getRegistrationUrl());
    $this->actingAs($invitee)->get('/convites/'.$invitation->id)->assertOk()->assertSee('Casa do Bruno');
    $this->post('/convites/'.$invitation->id.'/aceitar')->assertRedirect();
    expect($invitee->canAccessTenant($house))->toBeTrue();
    $this->post('/convites/'.$invitation->id.'/aceitar')->assertForbidden();
});

test('admin sends invitation to email from members page', function () {
    $this->withoutVite();
    [$admin, $house] = invitationScenario();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant($house);
    Livewire::test(HouseMembers::class)->callAction('inviteMember', data: ['email' => 'bruno@example.com'])->assertHasNoFormErrors()->assertSee('bruno@example.com');
    Notification::assertSentOnDemand(HouseInvitation::class, fn ($notification, $channels, $recipient) => $recipient->routes['mail'] === 'bruno@example.com');
});

/**
 * @return array{0: User, 1: House, 2: Invitation, 3: string}
 */
function invitationScenario(string $email = 'bruno@example.com', string $houseName = 'Casa'): array
{
    Notification::fake();
    $admin = User::factory()->create();
    $house = House::create(['name' => $houseName]);
    $house->memberships()->create(['user_id' => $admin->id, 'display_name' => $admin->name, 'role' => MembershipRole::Admin]);
    $invitation = app(SendInvitation::class)($admin, $house, $email);
    $url = '';
    Notification::assertSentOnDemand(HouseInvitation::class, function ($notification) use (&$url) {
        $url = $notification->url;

        return true;
    });
    parse_str(parse_url($url, PHP_URL_FRAGMENT), $fragment);

    return [$admin, $house, $invitation, $fragment['token']];
}
