<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;


#[Fillable(['ud_id', 'ud_user_id', 'ud_fullname', 'ud_mobile_number', 'ud_email', 'ud_created_at', 'ud_updated_at'])]
#[Hidden(['ud_created_at', 'ud_updated_at'])]
class UserDetails extends Model
{
    protected $hidden = ['ud_created_at', 'ud_updated_at'];

    use HasFactory, Notifiable, HasApiTokens;

    protected $primaryKey = 'ud_id';
    
    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'ud_created_at';

    public const UPDATED_AT = 'ud_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $userDetails): void {
            $userDetails->ud_id ??= (string) Str::uuid();
        });
    }
}
