<?php

namespace App\Filament\Pages;

use App\Actions\Houses\ManageMembership;
use App\Actions\Invitations\RevokeInvitation;
use App\Actions\Invitations\SendInvitation;
use App\Enums\MembershipRole;
use App\Models\House;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class HouseMembers extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.house-members';

    protected static ?string $title = 'Membros da casa';

    public function resendInvitationAction(): Action
    {
        return Action::make('resendInvitation')->label('Reenviar')->color('gray')->requiresConfirmation()
            ->visible(fn () => $this->house()->isAdmin(auth()->user()))->action(function (array $arguments) {
                $house = $this->house();
                Gate::authorize('update', $house);
                $invitation = $house->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->findOrFail($arguments['id'] ?? 0);
                app(SendInvitation::class)(auth()->user(), $house, $invitation->email);
            });
    }

    public function revokeInvitationAction(): Action
    {
        return Action::make('revokeInvitation')->label('Revogar')->color('danger')->requiresConfirmation()
            ->visible(fn () => $this->house()->isAdmin(auth()->user()))->action(function (array $arguments) {
                app(RevokeInvitation::class)(auth()->user(), $this->house(), (int) ($arguments['id'] ?? 0));
            });
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('inviteMember')->label('Convidar membro')->visible(fn () => $this->house()->isAdmin(auth()->user()))->schema([TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255)])->action(function (array $data) {
            app(SendInvitation::class)(auth()->user(), $this->house(), $data['email']);
        }), Action::make('leaveHouse')->label('Sair da casa')->color('gray')->requiresConfirmation()->action(function () {
            $house = $this->house();
            $member = $house->memberships()->where('user_id', auth()->id())->whereNull('left_at')->sole();
            app(ManageMembership::class)->remove(auth()->user(), $house, $member->id);
            Filament::setTenant(null);
            $this->redirect(Filament::getPanel('app')->getUrl());
        })];
    }

    public function house(): House
    {
        $house = Filament::getTenant();
        abort_unless($house instanceof House, 404);
        Gate::authorize('view', $house);

        return $house;
    }

    public function table(Table $table): Table
    {
        $house = $this->house();
        $admin = $house->isAdmin(auth()->user());

        return $table->query($house->memberships()->with('user')->getQuery())->columns([
            TextColumn::make('display_name')->label('Membro')->state(fn (Membership $record) => $record->label()),
            TextColumn::make('role')->label('Papel')->badge(),
            TextColumn::make('left_at')->label('Saiu em')->date('d/m/Y')->placeholder('Ativo'),
        ])->recordActions([
            Action::make('promoteMember')->label('Promover')->visible(fn (Membership $record) => $admin && $record->left_at === null && $record->role === MembershipRole::Member)->requiresConfirmation()
                ->action(function (Membership $record) {
                    app(ManageMembership::class)->setRole(auth()->user(), $this->house(), $record->id, MembershipRole::Admin);
                    $this->resetTable();
                }),
            Action::make('demoteMember')->label('Rebaixar')->visible(fn (Membership $record) => $admin && $record->left_at === null && $record->role === MembershipRole::Admin)->requiresConfirmation()
                ->action(function (Membership $record) {
                    app(ManageMembership::class)->setRole(auth()->user(), $this->house(), $record->id, MembershipRole::Member);
                    $this->resetTable();
                }),
            Action::make('removeMember')->label('Remover')->color('danger')->requiresConfirmation()->visible(fn (Membership $record) => $admin && $record->left_at === null)
                ->action(function (Membership $record) {
                    app(ManageMembership::class)->remove(auth()->user(), $this->house(), $record->id);
                    if ($record->user_id === auth()->id()) {
                        $this->redirect(Filament::getPanel('app')->getUrl());
                    } else {
                        $this->resetTable();
                    }
                }),
        ]);
    }
}
