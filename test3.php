<?php
$resource = App\Models\Resource::first();
if (!$resource) { echo "No resource\n"; return; }

$disk = Illuminate\Support\Facades\Storage::disk($resource->file_disk);
$path = $disk->path($resource->file_path);

$response = Illuminate\Support\Facades\Http::withToken(config('services.ai.token'))
    ->post(config('services.ai.url') . '/summarize', [
        'file_path' => $path,
        'file_type' => $resource->type,
    ]);

echo "AI Response Status: " . $response->status() . "\n";
echo "AI Response Body: " . $response->body() . "\n";
