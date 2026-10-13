<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$resource = App\Models\Resource::first();
if (!$resource) { echo "No resource"; exit; }

$disk = Storage::disk($resource->file_disk);
$path = $disk->path($resource->file_path);

$response = Illuminate\Support\Facades\Http::withToken(config('services.ai.token'))
    ->post(config('services.ai.url') . '/summarize', [
        'file_path' => $path,
        'file_type' => $resource->type,
    ]);

echo "AI Response Status: " . $response->status() . "\n";
echo "AI Response Body: " . $response->body() . "\n";
