<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'title',
        'slug',
        'summary',
        'content',
        'learning_objectives',
        'estimated_minutes',
        'order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'learning_objectives' => 'array',
            'estimated_minutes' => 'integer',
            'order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('order');
    }

    public function practiceTest(): HasOne
    {
        return $this->hasOne(Test::class)->where('type', 'lesson_practice');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
