<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('resources')
            ->orderBy('id')
            ->chunkById(100, function ($resources): void {
                foreach ($resources as $resource) {
                    $this->copyAndVerify('public', 'local', $resource, true);
                }
            });
    }

    public function down(): void
    {
        DB::table('resources')
            ->orderBy('id')
            ->chunkById(100, function ($resources): void {
                foreach ($resources as $resource) {
                    $this->copyAndVerify('local', 'public', $resource, false);
                }
            });
    }

    private function copyAndVerify(string $sourceName, string $targetName, object $resource, bool $deleteSource): void
    {
        $path = $resource->file_path;
        if (!str_starts_with($path, 'resources/')) {
            throw new RuntimeException("Unexpected resource file path: {$path}");
        }

        $source = Storage::disk($sourceName);
        $target = Storage::disk($targetName);
        if (!$source->exists($path)) {
            return;
        }

        if (!$target->exists($path)) {
            $stream = $source->readStream($path);
            if (!is_resource($stream)) {
                throw new RuntimeException("Unable to read resource file: {$path}");
            }

            try {
                $copied = $target->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }

            if (!$copied) {
                throw new RuntimeException("Unable to copy resource file: {$path}");
            }
        }

        $targetHash = hash_file('sha256', $target->path($path));
        if (!is_string($targetHash) || !hash_equals($resource->file_hash, $targetHash)) {
            throw new RuntimeException("Resource file hash mismatch: {$path}");
        }

        if ($deleteSource) {
            $source->delete($path);
        }
    }
};