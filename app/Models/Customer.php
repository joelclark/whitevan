<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'first_name',
    'last_name',
    'company',
    'email',
    'phone',
    'address_line_1',
    'address_line_2',
    'city',
    'state',
    'zip',
    'notes',
])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    /**
     * Normalize empty-string email to null so per-account uniqueness
     * treats blanks as absent. Mirrors prepareForValidation() in the
     * form request as a belt-and-suspenders for direct writes
     * (factories, seeders, tinker).
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = ($value === null || trim($value) === '')
            ? null
            : trim($value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_accessed_at' => 'datetime',
        ];
    }
}
