<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['a_id', 'a_employee_id', 'a_password_hash', 'a_role_id', 'a_designation_id', 'a_status', 'a_created_at', 'a_updated_at', 'a_last_login'])]
#[Hidden(['a_password_hash', 'remember_token'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $primaryKey = 'a_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'a_created_at';

    public const UPDATED_AT = 'a_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $admin): void {
            $admin->a_id ??= (string) Str::uuid();
        });
    }

    public function getAuthIdentifierName()
    {
        return 'a_employee_id';
    }

    public function getAuthIdentifier()
    {
        return $this->a_employee_id;
    }

    public function getAuthPassword()
    {
        return $this->a_password_hash;
    }

    public function getAuthPasswordName()
    {
        return 'a_password_hash';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'a_role_id', 'ro_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'a_password_hash' => 'hashed',
        ];
    }
}
