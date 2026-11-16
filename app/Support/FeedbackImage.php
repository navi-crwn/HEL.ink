<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The screenshot of a report: checked by its content (not its name), measured before it is opened, drawn again
 * with GD as a new JPEG (so EXIF and anything hidden in the file are gone) and kept on the private disk under a
 * path the server makes up. Nothing from the visitor becomes part of that path.
 */
final class FeedbackImage
{
    public const MAX_KB = 5120;

    /** A small file can claim a huge picture; decoding takes about 4 bytes a pixel. */
    public const MAX_PIXELS = 40_000_000;

    public const MAX_SIDE = 2560;

    private const TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    /**
     * Stores the picture and returns its path on the private disk.
     *
     * @throws FeedbackImageRejected with reason type | too_large | unreadable | no_room
     */
    public static function store(UploadedFile $file): string
    {
        $real = $file->getRealPath();
        if ($real === false || ! $file->isValid()) {
            throw new FeedbackImageRejected(in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'too_large' : 'unreadable');
        }
        if (($file->getSize() ?: 0) > self::MAX_KB * 1024) {
            throw new FeedbackImageRejected('too_large');
        }
        $info = @getimagesize($real); // reads the header only
        if ($info === false || ! in_array($info[2], self::TYPES, true)) {
            throw new FeedbackImageRejected('type');
        }
        [$w, $h, $type] = $info;
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELS || ! self::fitsMemory($w, $h)) {
            throw new FeedbackImageRejected('too_large');
        }
        $src = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($real),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($real),
            default => @imagecreatefromwebp($real),
        };
        if ($src === false) {
            throw new FeedbackImageRejected('unreadable');
        }
        if ($type === IMAGETYPE_JPEG) {
            $src = self::upright($src, $real);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // transparency becomes white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start();
        imagejpeg($dst, null, 88);
        $bytes = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);
        if ($bytes === '') {
            throw new FeedbackImageRejected('unreadable');
        }
        if (self::folderBytes() + strlen($bytes) > max(0, (int) config('hop.feedback.max_folder_mb')) * 1048576) {
            throw new FeedbackImageRejected('no_room');
        }
        $path = 'feedback/'.now()->format('Y/m').'/'.bin2hex(random_bytes(20)).'.jpg';
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    /** Bytes in the screenshot folder. */
    public static function folderBytes(): int
    {
        $disk = Storage::disk('local');
        $total = 0;
        foreach ($disk->allFiles('feedback') as $file) {
            $total += $disk->size($file);
        }

        return $total;
    }

    /** Whether decoding a w x h picture fits the memory PHP has left; a fatal error would be a 500 page. */
    private static function fitsMemory(int $w, int $h): bool
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1' || $limit === false) {
            return true;
        }
        $bytes = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));
        $bytes *= match ($unit) {
            'g' => 1073741824,
            'm' => 1048576,
            'k' => 1024,
            default => 1,
        };

        // The decoded picture, plus the resized copy and the encoder's working room.
        return $w * $h * 5 + 8_000_000 < $bytes - memory_get_usage();
    }

    /** A phone photo can say "turn me" in its EXIF; the stored copy has no EXIF, so the turn is done here. */
    private static function upright(\GdImage $img, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $img;
        }
        $turned = imagerotate($img, $angle, 0);

        return $turned === false ? $img : $turned;
    }
}
