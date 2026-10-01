<x-filament-panels::page>
    @if ($errors->any())
        <div role="alert" class="dv-error">{{ $errors->first() }}</div>
    @endif
    <p class="dv-intro">Cada morador tem sua parte. Administradores cuidam das contas e dos convites.</p>
    {{ $this->table }}
    @if ($this->house()->isAdmin(auth()->user()))
        <section class="dv-summary">
            <h2>Convites</h2>
            @forelse ($this->house()->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->orderByDesc('id')->get() as $invitation)
                <div class="dv-invitation"><span>{{ $invitation->email }}</span><span>{{ $invitation->expires_at->isFuture() ? 'Aguardando aceitação' : 'Expirado' }}</span>{{ ($this->resendInvitationAction)(['id' => $invitation->id]) }}{{ ($this->revokeInvitationAction)(['id' => $invitation->id]) }}</div>
            @empty
                <p class="dv-invite-empty"><img src="{{ asset('images/diddy/diddy-sem-convites.png') }}" alt="" width="132" height="132"><span>Nenhum convite pendente.</span></p>
            @endforelse
        </section>
    @endif
</x-filament-panels::page>
