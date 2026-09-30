<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_without_house_reaches_email_verification_from_app(): void
    {
        $this->withoutVite();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/app')->assertRedirect(Filament::getPanel('app')->getEmailVerificationPromptUrl());
        $this->get(Filament::getPanel('app')->getEmailVerificationPromptUrl())->assertOk();
        $this->assertFalse($user->can('create', House::class));
        $user->markEmailAsVerified();
        $this->get('/app')->assertRedirect(Filament::getPanel('app')->getTenantRegistrationUrl());
        $this->get(Filament::getPanel('app')->getTenantRegistrationUrl())->assertOk();
    }

    public function test_registration_shows_validation_error_for_missing_email(): void
    {
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->fillForm(['name' => 'Bruno', 'email' => null, 'password' => 'valid-password-123', 'passwordConfirmation' => 'valid-password-123'])->call('register')->assertHasFormErrors(['email']);
    }

    public function test_registration_normalizes_email_before_checking_uniqueness(): void
    {
        $this->withoutVite();
        User::factory()->create(['email' => 'bruno@example.com']);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Livewire::test(Filament::getPanel('app')->getRegistrationRouteAction())->fillForm(['name' => 'Bruno', 'email' => 'BRUNO@example.com', 'password' => 'valid-password-123', 'passwordConfirmation' => 'valid-password-123'])->call('register')->assertHasFormErrors(['email'])->assertSee('já está em uso');
        $this->assertGuest();
    }

    public function test_login_rejects_wrong_password_then_authenticates_without_house(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['password' => 'valid-password-123']);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $page = Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'wrong-password'])->call('authenticate')->assertHasFormErrors();
        $this->assertGuest();
        $page->fillForm(['email' => $user->email, 'password' => 'valid-password-123'])->call('authenticate')->assertHasNoFormErrors()->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->get('/app')->assertRedirect();
    }

    public function test_password_reset_changes_password_through_filament_surface(): void
    {
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
        $this->assertTrue(Hash::check('new-valid-password-123', $user->fresh()->password));
    }
}
