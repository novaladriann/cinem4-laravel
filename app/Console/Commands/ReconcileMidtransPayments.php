<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\MidtransSandboxService;
use Illuminate\Console\Command;
use Throwable;

class ReconcileMidtransPayments extends Command
{
    protected $signature = 'payments:reconcile {--limit=30 : Maksimal transaksi per proses}';

    protected $description = 'Sinkronkan status transaksi Midtrans Sandbox yang belum final';

    public function handle(MidtransSandboxService $payments): int
    {
        $limit = max(1, min(100, (int) $this->option('limit')));
        $count = 0;
        $errors = 0;

        Booking::query()
            ->where('status', 'pending')
            ->whereNotNull('midtrans_order_id')
            ->where('midtrans_started_at', '<=', now()->subMinutes(2))
            ->orderBy('midtrans_started_at')
            ->limit($limit)
            ->get()
            ->each(function (Booking $booking) use ($payments, &$count, &$errors): void {
                try {
                    $payments->reconcile($booking);
                    $count++;
                } catch (Throwable $exception) {
                    report($exception);
                    $errors++;
                }
            });

        $this->info("Midtrans: {$count} berhasil diperiksa, {$errors} perlu pemeriksaan ulang.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
