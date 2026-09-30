<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BillStatus: int implements HasColor, HasLabel
{
    case Pending = 0;
    case Partial = 1;
    case Paid = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pendente', self::Partial => 'Parcial', self::Paid => 'Pago',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'danger', self::Partial => 'warning', self::Paid => 'success',
        };
    }
}
