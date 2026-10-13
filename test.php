<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$resource = App\Models\Resource::first();
if (!$resource) { echo "No resource"; exit; }

$request = Illuminate\Http\Request::create('/api/resources/'.$resource->id.'/generate-description', 'POST');
$request->setUserResolver(function() use ($resource) { return $resource->user; });

$response = $kernel->handle($request);
echo "STATUS: " . $response->getStatusCode() . "\n";
echo "BODY: " . $response->getContent() . "\n";
