<?php

namespace App\Notifications;

use App\Models\ContributionRequest;
use App\Models\Resource;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResourceSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Resource $resource,
        private readonly ContributionRequest $contributionRequest,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'resource_id' => $this->resource->id,
            'contribution_request_id' => $this->contributionRequest->id,
            'title' => $this->resource->title,
            'message' => 'Nouvelle ressource à modérer',
            'url' => '/admin/moderation',
        ];
    }
}