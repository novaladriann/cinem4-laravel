<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Clock3, Ticket } from '@lucide/vue';
import { computed } from 'vue';

type Booking = {
    id_booking: number;
    seat_count: number;
    unit_price: string;
    total_price: string;
    status: string;
    expires_at: string | null;
    midtrans_order_id: string | null;
    midtrans_redirect_url: string | null;
    seats: { seat_number: number }[];
    schedule: {
        show_date: string;
        show_time: string;
        studio_name: string;
        movie: { title: string };
        cinema: { name: string };
    };
};

const props = defineProps<{ booking: Booking; displayStatus: string }>();
const page = usePage();
const paymentError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.payment,
);
const expiresAt = computed(() =>
    props.booking.expires_at
        ? new Date(props.booking.expires_at).toLocaleString('id-ID', {
              timeZone: 'Asia/Jakarta',
          }) + ' WIB'
        : '—',
);

function startPayment(): void {
    router.post(
        `/my-bookings/${props.booking.id_booking}/pay`,
        {},
        { preserveScroll: true },
    );
}

function refreshPayment(): void {
    router.post(
        `/my-bookings/${props.booking.id_booking}/refresh`,
        {},
        { preserveScroll: true },
    );
}

function cancelBooking(): void {
    if (!window.confirm('Batalkan reservasi ini dan lepaskan semua kursi?'))
        return;
    router.patch(`/my-bookings/${props.booking.id_booking}/cancel`);
}
</script>

<template>
    <Head :title="`Reservasi #${booking.id_booking} | CINEM4`" />
    <div class="min-h-screen bg-[#0c1018] text-slate-100">
        <main class="mx-auto max-w-2xl px-5 py-10">
            <Link
                href="/my-bookings"
                class="inline-flex min-h-11 items-center gap-2 text-sm text-slate-300 hover:text-white"
                ><ArrowLeft class="size-4" /> Reservasi saya</Link
            >
            <div
                class="mt-9 rounded-2xl border border-slate-700 bg-[#171c27] p-6 sm:p-9"
            >
                <div class="mb-6 flex items-center gap-3">
                    <Ticket class="size-7 text-amber-400" />
                    <p
                        class="text-xs font-bold tracking-widest text-amber-400 uppercase"
                    >
                        CINEM4 · Reservasi
                    </p>
                </div>
                <h1 class="text-2xl font-bold">
                    {{ booking.schedule.movie.title }}
                </h1>
                <p class="mt-3 text-sm text-slate-400">
                    {{ booking.schedule.cinema.name }} ·
                    {{ booking.schedule.studio_name }}
                </p>
                <p class="mt-2 text-sm text-slate-400">
                    {{ booking.schedule.show_date.slice(0, 10) }} ·
                    {{ booking.schedule.show_time.slice(0, 5) }} WIB
                </p>
                <dl
                    class="mt-7 grid gap-5 border-t border-slate-700 pt-6 sm:grid-cols-2"
                >
                    <div>
                        <dt class="text-xs text-slate-400">Nomor reservasi</dt>
                        <dd class="mt-2 font-semibold">
                            #{{ booking.id_booking }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Status</dt>
                        <dd class="mt-2 font-semibold">
                            {{
                                displayStatus === 'pending'
                                    ? 'Menunggu pembayaran'
                                    : displayStatus === 'expired'
                                      ? 'Kedaluwarsa'
                                      : displayStatus === 'cancelled'
                                        ? 'Dibatalkan'
                                        : 'Dibayar'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Nomor kursi</dt>
                        <dd class="mt-2 font-semibold">
                            {{
                                booking.seats
                                    .map((seat) => seat.seat_number)
                                    .join(', ')
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Total harga</dt>
                        <dd class="mt-2 text-xl font-bold text-amber-300">
                            Rp
                            {{
                                Number(booking.total_price).toLocaleString(
                                    'id-ID',
                                )
                            }}
                        </dd>
                    </div>
                </dl>
                <div
                    v-if="displayStatus === 'pending'"
                    class="mt-7 rounded-xl border border-amber-400/30 bg-amber-400/10 p-4"
                >
                    <p
                        class="flex items-center gap-2 font-semibold text-amber-300"
                    >
                        <Clock3 class="size-4" /> Kursi sedang ditahan
                    </p>
                    <p class="mt-2 text-sm leading-relaxed text-slate-300">
                        Masa reservasi hingga {{ expiresAt }}. Pembayaran
                        menggunakan Midtrans Sandbox (uang simulasi). Jika sudah
                        masuk halaman pembayaran, periksa statusnya sebelum
                        mencoba lagi.
                    </p>
                    <p
                        v-if="paymentError"
                        role="alert"
                        class="mt-3 text-sm text-red-300"
                    >
                        {{ paymentError }}
                    </p>
                    <button
                        v-if="!booking.midtrans_order_id"
                        type="button"
                        class="mt-4 min-h-11 w-full rounded-lg bg-amber-400 px-5 font-semibold text-slate-950 hover:bg-amber-300"
                        @click="startPayment"
                    >
                        Bayar dengan Midtrans Sandbox
                    </button>
                    <button
                        v-if="booking.midtrans_order_id"
                        type="button"
                        class="mt-4 min-h-11 w-full rounded-lg border border-amber-400 px-5 font-semibold text-amber-300 hover:bg-amber-400/10"
                        @click="refreshPayment"
                    >
                        Periksa Status Pembayaran
                    </button>
                    <button
                        v-if="
                            booking.midtrans_order_id &&
                            booking.midtrans_redirect_url
                        "
                        type="button"
                        class="mt-3 min-h-11 w-full rounded-lg border border-slate-500 px-5 font-semibold text-slate-200 hover:bg-slate-700/40"
                        @click="startPayment"
                    >
                        Buka kembali checkout Midtrans
                    </button>
                    <button
                        v-if="!booking.midtrans_order_id"
                        type="button"
                        class="mt-4 min-h-11 rounded-lg border border-red-300/60 px-4 text-sm font-medium text-red-200 hover:bg-red-400/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-300"
                        @click="cancelBooking"
                    >
                        Batalkan reservasi
                    </button>
                </div>
                <p v-else class="mt-7 text-sm text-slate-400">
                    Reservasi ini tidak memiliki penahanan kursi aktif. Status
                    dibayar hanya ditentukan setelah konfirmasi Midtrans.
                </p>
            </div>
        </main>
    </div>
</template>
