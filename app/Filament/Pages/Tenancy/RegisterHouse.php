<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\MembershipRole;
use App\Models\House;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RegisterHouse extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Criar casa';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->label('Nome da casa')->required()->maxLength(255)->rules(['regex:/\S/'])->dehydrateStateUsing(fn (string $state) => trim($state)),
        ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            $house = House::create($data);
            $house->memberships()->create([
                'user_id' => auth()->id(),
                'role' => MembershipRole::Admin,
                'display_name' => auth()->user()->name,
            ]);

            return $house;
        });
    }
}
