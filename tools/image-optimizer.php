<?php
/**
 * image-optimizer.php — compress uploaded images and convert them to WebP.
 *
 * WebP is much smaller than JPEG/PNG at similar quality and supports
 * transparency (alpha), so logos/PNGs without background keep it. Used by the
 * CMS upload handler (cms/ajax/files.ajax.php) right after a file is stored.
 *
 * Strategy (best available wins, otherwise the original is kept untouched):
 *   1. cwebp binary (Google's encoder) — handles JPEG/PNG incl. alpha.
 *   2. PHP GD imagewebp() — when the GD build has WebP support.
 *   3. none → return the original file unchanged (never breaks the upload).
 *
 * CLI usage (optimize an existing file or a whole folder):
 *   php tools/image-optimizer.php path/to/image.jpg
 *   php tools/image-optimizer.php path/to/folder
 */

if (!function_exists('wpb_findCwebp')) {

/** Locate the cwebp binary (PATH, common installs, or next to the PHP binary). */
function wpb_findCwebp() {
    static $cached = false;
    if ($cached !== false) { return $cached; }
    $candidates = [
        '/opt/homebrew/bin/cwebp', '/usr/local/bin/cwebp', '/usr/bin/cwebp',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'cwebp',
    ];
    if (function_exists('shell_exec')) {
        $isWin = (stripos(PHP_OS, 'WIN') === 0);
        $which = @shell_exec(($isWin ? 'where' : 'command -v') . ' cwebp ' . ($isWin ? '2>nul' : '2>/dev/null'));
        if ($which) {
            $first = trim(strtok($which, "\n"));
            if ($first !== '') { array_unshift($candidates, $first); }
        }
    }
    foreach ($candidates as $c) {
        if ($c && @is_file($c) && @is_executable($c)) { return $cached = $c; }
    }
    return $cached = null;
}

/**
 * Optimize an image in place: convert raster photos (jpg/jpeg/png) to a
 * compressed WebP next to the original, delete the original, and return the new
 * .webp path. Non-raster files (gif/svg/webp/pdf/…) or failures return the
 * original path unchanged, so the upload never breaks.
 *
 * @param string $path  Absolute path to the saved file.
 * @param array  $opts  quality (1-100, default 82), maxWidth (px, default 1920; 0 = no resize).
 * @return string  Path to the optimized file (or the original on no-op).
 */
function wpb_optimizeImage($path, array $opts = []) {
    if (!is_string($path) || !is_file($path)) { return $path; }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) { return $path; } // only raster photos

    $quality  = isset($opts['quality'])  ? max(1, min(100, (int)$opts['quality'])) : 82;
    $maxWidth = array_key_exists('maxWidth', $opts) ? (int)$opts['maxWidth'] : 1920;

    $webp = preg_replace('/\.[^.\/\\\\]+$/', '', $path) . '.webp';

    // Downscale only if the image is wider than maxWidth.
    $info   = @getimagesize($path);
    $width  = is_array($info) ? ($info[0] ?? 0) : 0;
    $resize = ($maxWidth > 0 && $width > $maxWidth) ? $maxWidth : 0;

    $ok = false;

    // 1) cwebp binary.
    $bin = wpb_findCwebp();
    if ($bin && function_exists('exec')) {
        $null = (stripos(PHP_OS, 'WIN') === 0) ? '2>nul' : '2>/dev/null';
        $cmd = escapeshellarg($bin) . ' -quiet -q ' . $quality;
        if ($resize) { $cmd .= ' -resize ' . $resize . ' 0'; }
        $cmd .= ' ' . escapeshellarg($path) . ' -o ' . escapeshellarg($webp) . ' ' . $null;
        @exec($cmd, $out, $code);
        $ok = ($code === 0 && is_file($webp) && filesize($webp) > 0);
    }

    // 2) GD fallback (only if this PHP build has WebP support).
    if (!$ok && function_exists('imagewebp')) {
        $ok = wpb_gdToWebp($path, $webp, $quality, $resize, $ext);
    }

    if ($ok) {
        // Keep WebP only if both sizes are readable and it actually saved space;
        // otherwise discard it (filesize() returning false must not delete the original).
        $webpSize = @filesize($webp);
        $pathSize = @filesize($path);
        if ($webpSize !== false && $pathSize !== false && $webpSize < $pathSize) {
            @unlink($path);
            return $webp;
        }
        @unlink($webp);
    }
    return $path;
}

/** GD-based JPEG/PNG → WebP (used only when imagewebp() exists). */
function wpb_gdToWebp($src, $dst, $quality, $resize, $ext) {
    $img = ($ext === 'png') ? @imagecreatefrompng($src) : @imagecreatefromjpeg($src);
    if (!$img) { return false; }
    if ($ext === 'png') { imagepalettetotruecolor($img); imagealphablending($img, false); imagesavealpha($img, true); }
    if ($resize) {
        $w = imagesx($img); $h = imagesy($img);
        if ($w > $resize) {
            $nh = (int) round($h * ($resize / $w));
            $tmp = imagecreatetruecolor($resize, $nh);
            imagealphablending($tmp, false); imagesavealpha($tmp, true);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $resize, $nh, $w, $h);
            imagedestroy($img); $img = $tmp;
        }
    }
    $ok = @imagewebp($img, $dst, $quality);
    imagedestroy($img);
    return $ok && is_file($dst) && filesize($dst) > 0;
}

} // function_exists guard

// ---- CLI mode ----------------------------------------------------------------
if (PHP_SAPI === 'cli' && isset($argv[1]) && realpath($argv[0]) === realpath(__FILE__)) {
    $target = $argv[1];
    $files  = is_dir($target)
        ? array_merge(glob(rtrim($target, '/') . '/*.{jpg,jpeg,png}', GLOB_BRACE) ?: [])
        : [$target];
    $saved = 0; $count = 0;
    foreach ($files as $f) {
        $before = @filesize($f) ?: 0;
        $out = wpb_optimizeImage($f);
        if ($out !== $f) { $count++; $saved += $before - (@filesize($out) ?: 0); }
    }
    fwrite(STDOUT, "Optimized {$count} image(s); saved " . round($saved / 1024) . " KB.\n");
    fwrite(STDOUT, wpb_findCwebp() ? "Encoder: cwebp (" . wpb_findCwebp() . ")\n"
                                   : (function_exists('imagewebp') ? "Encoder: GD imagewebp\n" : "No WebP encoder available — files left unchanged.\n"));
}
