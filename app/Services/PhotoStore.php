<?php

declare(strict_types=1);

/**
 * Uploaded photos, stored outside the web root (Rules/CONVENTIONS.md §7.5).
 *
 * Every file is checked with finfo (never the browser-supplied name or type),
 * re-encoded through GD so nothing but pixel data survives, and written under a
 * generated name. Nothing here ever echoes or reads a superglobal — the
 * controller normalises $_FILES and passes plain arrays in.
 *
 * Item photos, handover photos and damage evidence all share this class; the
 * folder argument is what separates them.
 */
final class PhotoStore
{
    /** Longest edge kept, in pixels — phone photos shrink, small ones are untouched. */
    private const MAX_EDGE = 1600;

    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<string, true> MIME types finfo must report. */
    private const ALLOWED_MIME = [
        'image/jpeg' => true,
        'image/png'  => true,
        'image/webp' => true,
    ];

    /** Absolute path of the upload root, e.g. <project>/storage/uploads. */
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
    }

    /**
     * Validate and store a batch, returning the paths to record on the row.
     *
     * @param  list<array{name?: string, tmp_name?: string, error?: int, size?: int}> $uploads
     * @param  string                                                                 $folder  e.g. 'item-photos'
     * @param  string                                                                 $field   field name for error messages
     * @return list<string> paths relative to the upload root
     *
     * @throws ValidationException when a file is unusable or the batch is too large
     */
    public function storeMany(array $uploads, string $folder, string $field, int $limit): array
    {
        $usable = array_values(array_filter(
            $uploads,
            static fn (array $u): bool => ($u['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));

        if ($usable === []) {
            return [];
        }

        if (count($usable) > $limit) {
            throw ValidationException::field($field, sprintf('Upload %d photos at most.', $limit));
        }

        $stored = [];

        foreach ($usable as $upload) {
            $stored[] = $this->storeOne($upload, $folder, $field);
        }

        return $stored;
    }

    /**
     * Absolute path of a stored file, or null when the path is malformed or
     * the file is gone. The pattern check is what stops "../" ever resolving
     * outside the upload root.
     */
    public function absolutePath(string $relativePath): ?string
    {
        if (preg_match('#^[a-z0-9-]+/[a-f0-9]{32}\.jpg$#', $relativePath) !== 1) {
            return null;
        }

        $absolute = $this->root . '/' . $relativePath;

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * Remove a stored file. Missing files are not an error — the row is the
     * record of truth, and a half-deleted upload must not block an edit.
     */
    public function delete(string $relativePath): void
    {
        $absolute = $this->absolutePath($relativePath);

        if ($absolute !== null) {
            @unlink($absolute);
        }
    }

    /**
     * @param  array{name?: string, tmp_name?: string, error?: int, size?: int} $upload
     * @return string path relative to the upload root
     *
     * @throws ValidationException
     */
    private function storeOne(array $upload, string $folder, string $field): string
    {
        $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw ValidationException::field($field, 'That photo is too large — 5 MB per photo.');
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw ValidationException::field($field, 'That photo did not upload. Try again.');
        }

        $temporary = (string) ($upload['tmp_name'] ?? '');

        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw ValidationException::field($field, 'That photo did not upload. Try again.');
        }

        if ((int) ($upload['size'] ?? 0) > self::MAX_BYTES) {
            throw ValidationException::field($field, 'That photo is too large — 5 MB per photo.');
        }

        // The browser-supplied type and extension are both ignored; finfo reads
        // the bytes (§7.5).
        $info = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $info->file($temporary);

        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw ValidationException::field($field, 'Photos must be JPG, PNG or WebP files.');
        }

        if (!function_exists('imagecreatefromstring')) {
            throw ValidationException::field($field, 'Photo uploads need PHP\'s GD extension. Enable it in php.ini.');
        }

        $image = @imagecreatefromstring((string) file_get_contents($temporary));

        if ($image === false) {
            throw ValidationException::field($field, 'That file is not a readable image.');
        }

        $image = $this->downscale($image);

        $relativePath = $folder . '/' . bin2hex(random_bytes(16)) . '.jpg';
        $absolutePath = $this->root . '/' . $relativePath;

        if (!is_dir(dirname($absolutePath)) && !mkdir(dirname($absolutePath), 0775, true) && !is_dir(dirname($absolutePath))) {
            imagedestroy($image);

            throw ValidationException::field($field, 'Could not save the photo. Tell an administrator.');
        }

        // Re-encoding is the sanitising step: only decoded pixels are written,
        // so any payload hidden in the original bytes is dropped.
        $written = imagejpeg($image, $absolutePath, 85);
        imagedestroy($image);

        if ($written === false) {
            throw ValidationException::field($field, 'Could not save the photo. Tell an administrator.');
        }

        return $relativePath;
    }

    /**
     * @param  GdImage $image
     * @return GdImage
     */
    private function downscale(GdImage $image): GdImage
    {
        $longestEdge = max(imagesx($image), imagesy($image));

        if ($longestEdge <= self::MAX_EDGE) {
            return $image;
        }

        $scaled = imagescale($image, (int) round(imagesx($image) * self::MAX_EDGE / $longestEdge));

        if ($scaled === false) {
            return $image;
        }

        imagedestroy($image);

        return $scaled;
    }
}
