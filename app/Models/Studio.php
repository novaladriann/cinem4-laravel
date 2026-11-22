<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studio extends Model
{
    protected $primaryKey = 'id_studio';

    protected $fillable = [
        'id_cinema',
        'name',
        'seat_capacity',
        'turnaround_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'seat_capacity' => 'integer',
            'turnaround_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Cinema, $this> */
    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class, 'id_cinema', 'id_cinema');
    }

    /** @return HasMany<Schedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'id_studio', 'id_studio');
    }
}
