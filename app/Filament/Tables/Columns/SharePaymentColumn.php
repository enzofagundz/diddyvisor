<?php

namespace App\Filament\Tables\Columns;

use Filament\Tables\Columns\CheckboxColumn;

class SharePaymentColumn extends CheckboxColumn
{
    public function toEmbeddedHtml(): string
    {
        return $this->getState() === null ? '<span aria-label="Não se aplica">—</span>' : parent::toEmbeddedHtml();
    }
}
