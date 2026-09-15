<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Per-platform "current / minimum supported" build of the field app — see `AppVersionController`. */
#[Fillable(['av_platform', 'av_latest_version', 'av_latest_build', 'av_min_supported_build', 'av_update_url', 'av_release_notes', 'av_updated_at', 'av_updated_by'])]
class AppVersion extends Model
{
    public const PLATFORMS = ['android', 'ios'];

    protected $table = 'app_versions';

    protected $primaryKey = 'av_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->av_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'av_latest_build' => 'integer',
            'av_min_supported_build' => 'integer',
            'av_updated_at' => 'datetime',
        ];
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return [
            'platform' => $this->av_platform,
            'latest_version' => $this->av_latest_version,
            'latest_build' => $this->av_latest_build,
            'min_supported_build' => $this->av_min_supported_build,
            'update_url' => $this->av_update_url,
            'release_notes' => $this->av_release_notes,
            'updated_at' => $this->av_updated_at?->toISOString(),
        ];
    }
}
