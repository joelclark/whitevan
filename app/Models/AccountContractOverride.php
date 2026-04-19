<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\AccountContractOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'body',
])]
class AccountContractOverride extends Model
{
    /** @use HasFactory<AccountContractOverrideFactory> */
    use BelongsToAccount, HasFactory;
}
