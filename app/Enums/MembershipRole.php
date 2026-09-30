<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MembershipRole: int implements HasLabel
{
    case Member = 0;
    case Admin = 1;

    public function getLabel(): string
    {
        return $this === self::Admin ? 'Administrador' : 'Membro';
    }
}
