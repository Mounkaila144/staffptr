<?php

namespace App\Services\Platform;

use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;
use Throwable;

class PrivateImageProcessor
{
    /** @return array{contents: string, mime_type: string, extension: string} */
    public function normalize(string $filePath, string $mimeType): array
    {
        try {
            $image = $this->manager($mimeType)->read($filePath)->orient();
            $encoded = match ($mimeType) {
                'image/jpeg' => $image->toJpeg(quality: 85),
                'image/png' => $image->toPng(),
                'image/webp' => $image->toWebp(quality: 85),
                'image/heic', 'image/heif' => $image->toJpeg(quality: 85),
                default => throw ValidationException::withMessages([
                    'file' => "Ce format d'image n'est pas accepté.",
                ]),
            };
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => 'Cette image ne peut pas être lue. Choisissez une autre image.',
            ]);
        }

        $convertedToJpeg = in_array($mimeType, ['image/heic', 'image/heif'], true);

        return [
            'contents' => (string) $encoded,
            'mime_type' => $convertedToJpeg ? 'image/jpeg' : $mimeType,
            'extension' => $convertedToJpeg ? 'jpg' : match ($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            },
        ];
    }

    public function thumbnail(string $contents): string
    {
        try {
            return (string) $this->manager('image/jpeg')
                ->read($contents)
                ->orient()
                ->scaleDown(width: 480, height: 480)
                ->toJpeg(quality: 82);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => "La vignette de cette image n'a pas pu être créée.",
            ]);
        }
    }

    private function manager(string $mimeType): ImageManager
    {
        if (in_array($mimeType, ['image/heic', 'image/heif'], true)) {
            if (! extension_loaded('imagick')) {
                throw ValidationException::withMessages([
                    'file' => 'Le format HEIC est temporairement indisponible. Convertissez la photo en JPEG.',
                ]);
            }

            return ImageManager::imagick(autoOrientation: true, decodeAnimation: false);
        }

        return extension_loaded('imagick')
            ? ImageManager::imagick(autoOrientation: true, decodeAnimation: false)
            : ImageManager::gd(autoOrientation: true, decodeAnimation: false);
    }
}
