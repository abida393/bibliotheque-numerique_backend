<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContributionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'resource_id',
        'moderator_id',
        'status',
        'comment',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    // --- Relations ---

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
