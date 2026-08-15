<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['d_id', 'd_designation_name', 'd_rank_order', 'd_description', 'd_status', 'd_created_at', 'd_updated_at'])]
class Designations extends Model
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $primaryKey = 'd_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'd_created_at';

    public const UPDATED_AT = 'd_updated_at';

    protected static function booted(): void
    {
        static::creating(function (self $designation): void {
            $designation->d_id ??= (string) Str::uuid();
        });
    }
}
