<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        foreach ($public->allFiles('resources') as $path) {
            if (!str_starts_with($path, 'resources/')) {
                throw new RuntimeException("Unexpected resource file path: {$path}");
            }

            if (!$private->exists($path)) {
                $stream = $public->readStream($path);
                if (!is_resource($stream)) {
                    throw new RuntimeException("Unable to read resource file: {$path}");
                }

                try {
                    $copied = $private->writeStream($path, $stream);
                } finally {
                    fclose($stream);
                }

                if (!$copied) {
                    throw new RuntimeException("Unable to copy resource file: {$path}");
                }
            }

            $publicHash = hash_file('sha256', $public->path($path));
            $privateHash = hash_file('sha256', $private->path($path));
            if (!is_string($publicHash) || !is_string($privateHash) || !hash_equals($publicHash, $privateHash)) {
                throw new RuntimeException("Resource file hash mismatch: {$path}");
            }

            $public->delete($path);
        }
    }

    public function down(): void
    {
        // Keep resource files private during rollbacks.
    }
};