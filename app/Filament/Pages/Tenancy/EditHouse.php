<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\House;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditHouse extends EditTenantProfile
{
    protected function getHeaderActions(): array
    {
        return [Action::make('deleteHouse')->label('Excluir casa')->color('danger')->requiresConfirmation()
            ->modalDescription('A casa e suas associações serão excluídas. Somente casas sem contas podem ser excluídas.')
            ->disabled(fn () => Filament::getTenant()->bills()->exists())
            ->action(function () {
                $id = Filament::getTenant()->id;
                DB::transaction(function () use ($id) {
                    $house = House::query()->lockForUpdate()->findOrFail($id);
                    Gate::authorize('delete', $house);
                    if ($house->bills()->exists()) {
                        throw ValidationException::withMessages(['house' => 'Exclua as contas antes de excluir a casa.']);
                    }
                    $house->delete();
                });
                Filament::setTenant(null);
                $this->redirect(Filament::getPanel('app')->getUrl());
            })];
    }

    public static function getLabel(): string
    {
        return 'Configurações da casa';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->label('Nome da casa')->required()->maxLength(255)->rules(['regex:/\S/'])->dehydrateStateUsing(fn (string $state) => trim($state)),
        ]);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data) {
            $house = House::query()->lockForUpdate()->findOrFail($record->id);
            Gate::authorize('update', $house);
            $house->update($data);

            return $house;
        });
    }
}
