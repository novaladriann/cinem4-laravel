<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Booking extends Model
{
    protected $primaryKey = 'id_booking';

    protected $fillable = [
        'id_user',
        'id_schedule',
        'seat_count',
        'unit_price',
        'total_price',
        'status',
        'expires_at',
        'paid_at',
        'midtrans_order_id',
        'midtrans_redirect_url',
        'midtrans_started_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_count' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'midtrans_started_at' => 'datetime',
        ];
    }

    public function needsPaymentReview(): bool
    {
        return DB::table('payment_reviews')->where('id_booking', $this->id_booking)->exists();
    }

    /** @return HasOne<Ticket, $this> */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class, 'id_booking', 'id_booking');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    /** @return BelongsTo<Schedule, $this> */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'id_schedule', 'id_schedule');
    }

    /** @return HasMany<BookingSeat, $this> */
    public function seats(): HasMany
    {
        return $this->hasMany(BookingSeat::class, 'id_booking', 'id_booking');
    }
}
