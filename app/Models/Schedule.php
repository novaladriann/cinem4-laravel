<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $primaryKey = 'id_schedule';

    protected $fillable = [
        'id_movie',
        'id_cinema',
        'studio_name',
        'show_date',
        'show_time',
        'price',
        'seat_capacity',
        'booking_close_minutes',
        'status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'show_date' => 'date',
            'price' => 'decimal:2',
            'seat_capacity' => 'integer',
            'booking_close_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'id_movie', 'id_movie');
    }

    /** @return BelongsTo<Cinema, $this> */
    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class, 'id_cinema', 'id_cinema');
    }
}
