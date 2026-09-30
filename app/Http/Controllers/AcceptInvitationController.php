<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Filament\Pages\MonthlyBills;
use App\Models\Invitation;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class AcceptInvitationController
{
    public function show(Request $request, int $invitation)
    {
        $panel = Filament::getPanel('app');
        $pending = $request->session()->get('pending_invitation');
        $record = null;
        if ($pending && $pending['id'] === $invitation) {
            $request->session()->put('url.intended', url('/convites/'.$invitation));
            if (! $request->user()) {
                return redirect($panel->getRegistrationUrl());
            }
            if (! $request->user()->hasVerifiedEmail()) {
                return redirect($panel->getEmailVerificationPromptUrl());
            }
            $record = Invitation::query()->with('house')->findOrFail($invitation);
            abort_unless($record->isOpen() && hash_equals($record->token_hash, $pending['hash']) && $record->email === $request->user()->email, 403);
        }

        return view('invitations.show', ['invitationId' => $invitation, 'invitation' => $record]);
    }

    public function prepare(Request $request, int $invitation)
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        $record = Invitation::query()->findOrFail($invitation);
        $hash = hash('sha256', $data['token']);
        abort_unless($record->isOpen() && hash_equals($record->token_hash, $hash), 403);
        $request->session()->put('pending_invitation', ['id' => $invitation, 'hash' => $hash]);
        $request->session()->put('url.intended', url('/convites/'.$invitation));

        return redirect($request->user() ? url('/convites/'.$invitation) : Filament::getPanel('app')->getRegistrationUrl());
    }

    public function accept(Request $request, int $invitation)
    {
        $pending = $request->session()->get('pending_invitation');
        abort_unless($request->user() && $pending && $pending['id'] === $invitation, 403);
        $house = app(AcceptInvitation::class)($request->user(), $invitation, $pending['hash']);
        $request->session()->forget(['pending_invitation', 'url.intended']);

        return redirect(MonthlyBills::getUrl(panel: 'app', tenant: $house));
    }
}
