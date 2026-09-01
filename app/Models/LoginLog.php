<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['ll_id', 'll_account_type', 'll_account_id', 'll_employee_id', 'll_successful', 'll_ip_address', 'll_user_agent', 'll_created_at'])]
class LoginLog extends Model
{
    public const TYPE_ADMIN = 'admin';

    public const TYPE_USER = 'user';

    protected $primaryKey = 'll_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public const CREATED_AT = 'll_created_at';

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->ll_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return ['ll_successful' => 'boolean'];
    }
}
