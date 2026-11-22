<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    protected $primaryKey = 'id_movie';

    protected $fillable = [
        'tmdb_id',
        'title',
        'slug',
        'genre',
        'duration_minute',
        'rating_age',
        'release_date',
        'status',
        'synopsis',
        'poster',
        'backdrop',
        'trailer_url',
        'is_featured',
        'show_in_hero',
        'hero_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'duration_minute' => 'integer',
            'release_date' => 'date',
            'is_featured' => 'boolean',
            'show_in_hero' => 'boolean',
            'hero_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'id_movie', 'id_movie');
    }
}
