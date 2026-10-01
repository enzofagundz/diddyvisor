<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Token;

class McpAccess extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.mcp-access';

    protected static ?string $title = 'Acesso MCP';

    protected static ?string $navigationLabel = 'Acesso MCP';

    public ?string $plainTextToken = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateToken')
                ->label('Gerar token')
                ->schema([
                    TextInput::make('name')->label('Nome')->required()->maxLength(255),
                ])
                ->action(function (array $data): void {
                    $this->plainTextToken = auth()->user()
                        ->createToken($data['name'], [Registrar::OAUTH_SCOPE])
                        ->accessToken;
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(auth()->user()->tokens()->getQuery())
            ->columns([
                TextColumn::make('name')->label('Nome'),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i'),
                TextColumn::make('expires_at')->label('Expira em')->dateTime('d/m/Y H:i')->placeholder('Nunca'),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revogar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Token $record) => $record->delete()),
            ]);
    }
}
