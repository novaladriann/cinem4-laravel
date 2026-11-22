<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    protected $primaryKey = 'id_schedule';

    protected $fillable = [
        'id_movie',
        'id_cinema',
        'id_studio',
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

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'id_schedule', 'id_schedule');
    }

    /** @return BelongsTo<Movie, $this> */
    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class, 'id_movie', 'id_movie');
    }

    /** @return BelongsTo<Studio, $this> */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class, 'id_studio', 'id_studio');
    }

    /** @return BelongsTo<Cinema, $this> */
    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class, 'id_cinema', 'id_cinema');
    }
}
