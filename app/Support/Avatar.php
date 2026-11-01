<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profile photos: re-encoded as a 400x400 JPEG (so nothing but pixels is
 * kept, no EXIF, no odd formats) on the public disk at avatars/{id}-{rand}.jpg.
 */
class Avatar
{
    public const SIZE = 400;

    public static function store(User $user, UploadedFile $file): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($src === false) {
            throw new \RuntimeException('That file is not an image we can read.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), self::SIZE, self::SIZE, $side, $side);
        ob_start();
        imagejpeg($dst, null, 88);
        $bytes = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        $path = 'avatars/'.$user->id.'-'.Str::lower(Str::random(10)).'.jpg';
        Storage::disk('public')->put($path, $bytes, 'public');
        self::delete($user);

        return $path;
    }

    public static function delete(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete(self::diskPath($user->avatar_path));
        }
    }

    /**
     * The picture as every consumer needs it: an absolute URL on the hub host
     * (hub.hel.ink), where the public disk is served. The apps run on other hosts
     * (link., bio., pixel., note.), so a bare "/storage/avatars/x.jpg" would be asked
     * of the wrong host and answer 404. Accepts what the columns have held over time:
     * "avatars/x.jpg", "/storage/avatars/x.jpg", "storage/avatars/x.jpg", a
     * protocol-relative "//host/x" or a full URL (Google pictures), which is kept.
     */
    public static function url(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }

        return hub_url('storage/'.self::diskPath($value));
    }

    /** The path on the public disk for a stored value: "/storage/avatars/x.jpg" and "avatars/x.jpg" both give "avatars/x.jpg". */
    public static function diskPath(string $value): string
    {
        $path = ltrim(trim($value), '/');

        return preg_replace('#^(?:public/)?(?:storage/)?#', '', $path) ?? $path;
    }
}
