<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Resource extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'authors',
        'type',
        'category_id',
        'user_id',
        'parent_resource_id',
        'language',
        'publication_date',
        'description',
        'file_path',
        'file_hash',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'date',
        ];
    }

    // --- Relations ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function parentResource()
    {
        return $this->belongsTo(Resource::class, 'parent_resource_id');
    }

    public function versions()
    {
        return $this->hasMany(Resource::class, 'parent_resource_id');
    }

    public function contributionRequest()
    {
        return $this->hasOne(ContributionRequest::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class);
    }

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    // --- Scopes utiles ---

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
