<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\AccountDepositOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'material_deposit_percent',
    'labor_deposit_percent',
])]
class AccountDepositOverride extends Model
{
    /** @use HasFactory<AccountDepositOverrideFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'material_deposit_percent' => 'integer',
            'labor_deposit_percent' => 'integer',
        ];
    }
}
