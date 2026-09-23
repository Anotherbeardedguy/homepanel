<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['start_at', 'end_at', 'price_ex_vat_eur_mwh', 'price_inc_vat_snt', 'fetched_at'])]
class ElectricityPrice extends Model
{
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'price_ex_vat_eur_mwh' => 'decimal:6',
            'price_inc_vat_snt' => 'decimal:6',
            'fetched_at' => 'datetime',
        ];
    }
}
