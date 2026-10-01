<?php

use App\Models\House;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\Login;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword as ResetPasswordPage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('unverified user without house reaches email verification from app', function () {
    $this->withoutVite();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get('/app')->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());
    $this->get(Filament::getPanel('app')->getEmailVerificationPromptUrl())->assertOk();
    expect($user->can('create', House::class))->toBeFalse();
    $user->markEmailAsVerified();
    $this->get('/app')->assertRedirect(Filament::getPanel('app')->getTenantRegistrationUrl());
    $this->get(Filament::getPanel('app')->getTenantRegistrationUrl())->assertOk();
});

test('registration shows validation error for missing email', function () {
    $this->withoutVite();
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->fillForm(['name' => 'Bruno', 'email' => null, 'password' => 'valid-password-123', 'passwordConfirmation' => 'valid-password-123'])->call('register')->assertHasFormErrors(['email']);
});

test('registration normalizes email before checking uniqueness', function () {
    $this->withoutVite();
    User::factory()->create(['email' => 'bruno@example.com']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->fillForm(['name' => 'Bruno', 'email' => 'BRUNO@example.com', 'password' => 'valid-password-123', 'passwordConfirmation' => 'valid-password-123'])->call('register')->assertHasFormErrors(['email'])->assertSee('já está em uso');
    $this->assertGuest();
});

test('login rejects wrong password then authenticates without house', function () {
    $this->withoutVite();
    $user = User::factory()->create(['password' => 'valid-password-123']);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $page = Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'wrong-password'])->call('authenticate')->assertHasFormErrors();
    $this->assertGuest();
    $page->fillForm(['email' => $user->email, 'password' => 'valid-password-123'])->call('authenticate')->assertHasNoFormErrors()->assertRedirect();
    $this->assertAuthenticatedAs($user);
    $this->get('/app')->assertRedirect();
});

test('password reset changes password through filament surface', function () {
    $this->withoutVite();
    Notification::fake();
    $user = User::factory()->create();
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::test(RequestPasswordReset::class)->fillForm(['email' => $user->email])->call('request')->assertHasNoFormErrors();
    $token = '';
    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
        $token = $notification->token;

        return true;
    });
    Livewire::test(ResetPasswordPage::class, ['email' => $user->email, 'token' => $token])->fillForm(['email' => $user->email, 'password' => 'new-valid-password-123', 'passwordConfirmation' => 'new-valid-password-123'])->call('resetPassword')->assertHasNoFormErrors()->assertRedirect();
    expect(Hash::check('new-valid-password-123', $user->fresh()->password))->toBeTrue();
});
