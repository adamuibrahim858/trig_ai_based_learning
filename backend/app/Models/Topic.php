<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'slug',
        'description',
        'week_number',
        'learning_objectives',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'learning_objectives' => 'array',
            'week_number' => 'integer',
            'order' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Test::class)->where('type', 'topic_assessment');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(TopicProgress::class);
    }
}
