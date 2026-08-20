<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['u_id', 'u_employee_id', 'u_password_hash', 'u_role_id','u_designation_id', 'u_status', 'u_created_at', 'u_updated_at', 'u_last_login'])]
#[Hidden(['u_password_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;
 
    protected $primaryKey = 'u_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'u_created_at';

    public const UPDATED_AT = 'u_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            $user->u_id ??= (string) Str::uuid();
        });
    }

    public function getAuthIdentifierName()
    {
        return 'u_employee_id';
    }

    public function getAuthIdentifier()
    {
        return $this->u_employee_id;
    }

    public function getAuthPassword()
    {
        return $this->u_password_hash;
    }

    public function getAuthPasswordName()
    {
        return 'u_password_hash';
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'u_role_id', 'ro_id');
    }

    public function details(): HasOne
    {
        return $this->hasOne(UserDetails::class, 'ud_user_id', 'u_id');
    }

    public function ranges(): BelongsToMany
    {
        return $this->belongsToMany(
            Ranges::class,
            'user_range_access',
            'ura_user_id',
            'ura_range_id',
            'u_id',
            'rn_id'
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'u_password_hash' => 'hashed',
        ];
    }
}
