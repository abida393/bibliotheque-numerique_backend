<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentChunk extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'resource_id',
        'text',
        'embedding_ref',
        'page_or_section',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // --- Relations ---

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }
}
