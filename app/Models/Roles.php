<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['ro_id', 'ro_name', 'ro_description', 'ro_status', 'ro_created_at', 'ro_updated_at'])]
#[Hidden(['ro_created_at', 'ro_updated_at'])]
class Roles extends Model
{
    protected $hidden = ['ro_created_at', 'ro_updated_at'];
    use HasFactory, Notifiable, HasApiTokens;

    protected $primaryKey = 'ro_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ro_created_at';

    public const UPDATED_AT = 'ro_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $role): void {
            $role->ro_id ??= (string) Str::uuid();
        });
    }
}
