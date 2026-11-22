<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cinema extends Model
{
    protected $primaryKey = 'id_cinema';

    protected $fillable = [
        'name',
        'city',
        'address',
        'image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Schedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'id_cinema', 'id_cinema');
    }
}
