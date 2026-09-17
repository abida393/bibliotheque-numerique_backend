<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'conversation_id',
        'question',
        'answer',
        'cited_sources',
    ];

    protected function casts(): array
    {
        return [
            'cited_sources' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // --- Relations ---

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }
}
