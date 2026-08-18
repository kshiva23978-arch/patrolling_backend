<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class PatrolPhotoService
{
    private const MAX_DIMENSION = 1920;

    private const JPEG_QUALITY = 70;

    /**
     * Compress an uploaded photo and store it privately on the given disk.
     * Returns the stored path and final byte size.
     *
     * @return array{path: string, size: int}
     */
    public function compressAndStore(UploadedFile $file, string $directory, string $disk = 'local'): array
    {
        $manager = new ImageManager(new Driver);

        $image = $manager->decodePath($file->getRealPath());
        $image->scaleDown(width: self::MAX_DIMENSION, height: self::MAX_DIMENSION);

        $encoded = $image->encode(new JpegEncoder(quality: self::JPEG_QUALITY));

        $path = trim($directory, '/').'/'.(string) Str::uuid().'.jpg';

        Storage::disk($disk)->put($path, (string) $encoded);

        return [
            'path' => $path,
            'size' => Storage::disk($disk)->size($path),
        ];
    }
}
