<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The screenshot of a report: checked by its content (not its name), measured before it is opened, drawn again
 * with GD as a new JPEG (so EXIF and anything hidden in the file are gone) and kept on the private disk under a
 * path the server makes up. Nothing from the visitor becomes part of that path.
 *
 * Decoding a picture takes much more memory than the file, and the server also runs other apps, so only one picture is
 * drawn again at a time (a cache lock); a visitor who does not get the turn within a few seconds is asked to try again.
 */
final class FeedbackImage
{
    public const MAX_KB = 5120;

    /** A small file can claim a huge picture. 25 million pixels still fit a 5K screenshot (14.7 million). */
    public const MAX_PIXELS = 25_000_000;

    public const MAX_SIDE = 2560;

    /** What decoding can take per pixel at most (a turned JPEG, WebP and 16-bit PNG hold two or three copies), and room for the rest. */
    private const BYTES_PER_PIXEL = 12;

    private const SPARE_BYTES = 32 * 1048576;

    private const TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    /**
     * Stores the picture and returns its path on the private disk.
     *
     * @throws FeedbackImageRejected with reason type | too_large (the file) | pixels (the picture) | unreadable | no_room | busy
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
        if ($w < 1 || $h < 1 || $w * $h > self::MAX_PIXELS) {
            throw new FeedbackImageRejected('pixels');
        }

        $lock = Cache::lock('feedback:decode', 60);
        try {
            $lock->block(max(0, (int) config('hop.feedback.decode_wait_seconds', 8)));
        } catch (LockTimeoutException) {
            throw new FeedbackImageRejected('busy');
        }
        // Only one picture is drawn at a time, so this request may take more memory than a normal one (a 4K screenshot needs more than
        // the 128 MB a server starts with). The limit goes back when the picture is done.
        $before = ini_get('memory_limit');
        $target = self::limitBytes((string) config('hop.feedback.decode_memory', '512M'));
        $raised = false;
        if ($before !== false && self::limitBytes($before) !== -1 && self::limitBytes($before) < $target) {
            $raised = @ini_set('memory_limit', (string) $target) !== false;
        }
        try {
            // Asked inside the turn, so the memory that is left is what this picture really has.
            if (! self::fitsMemory($w, $h)) {
                throw new FeedbackImageRejected('pixels');
            }
            $bytes = self::draw($real, $type);
        } finally {
            if ($raised) {
                @ini_set('memory_limit', (string) $before);
            }
            $lock->release();
        }

        if (self::folderBytes() + strlen($bytes) > max(0, (int) config('hop.feedback.max_folder_mb')) * 1048576) {
            throw new FeedbackImageRejected('no_room');
        }
        $path = 'feedback/'.now()->format('Y/m').'/'.bin2hex(random_bytes(20)).'.jpg';
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    /** The picture drawn again as JPEG bytes (the longest side at most MAX_SIDE, transparency as white, EXIF gone). */
    private static function draw(string $path, int $type): string
    {
        $src = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            default => @imagecreatefromwebp($path),
        };
        if ($src === false) {
            throw new FeedbackImageRejected('unreadable');
        }
        if ($type === IMAGETYPE_JPEG) {
            $src = self::upright($src, $path);
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($tw, $th);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // transparency becomes white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        unset($src); // the big picture goes before it is encoded
        ob_start();
        imagejpeg($dst, null, 88);
        $bytes = (string) ob_get_clean();
        unset($dst);
        if ($bytes === '') {
            throw new FeedbackImageRejected('unreadable');
        }

        return $bytes;
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
        $bytes = self::limitBytes((string) ini_get('memory_limit'));
        if ($bytes === -1) {
            return true;
        }

        return $w * $h * self::BYTES_PER_PIXEL + self::SPARE_BYTES < $bytes - memory_get_usage();
    }

    /** A memory_limit setting ("128M", "1G", "-1", a plain number of bytes) as bytes; -1 for no limit. */
    private static function limitBytes(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '' || $limit === '-1') {
            return -1;
        }
        $bytes = (int) $limit;

        return $bytes * match (strtolower(substr($limit, -1))) {
            'g' => 1073741824,
            'm' => 1048576,
            'k' => 1024,
            default => 1,
        };
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
        if ($turned === false) {
            return $img;
        }
        unset($img); // PHP frees the first bitmap once nothing holds it (imagedestroy does nothing since PHP 8)

        return $turned;
    }
}
