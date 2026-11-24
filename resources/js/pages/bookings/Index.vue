<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Ticket } from '@lucide/vue';
import CinemaNav from '@/components/CinemaNav.vue';
type Booking = {
    id_booking: number;
    status: string;
    expires_at: string | null;
    midtrans_order_id: string | null;
    total_price: string;
    seats: { seat_number: number }[];
    schedule: {
        show_date: string;
        show_time: string;
        movie: { title: string };
        cinema: { name: string };
    };
};
defineProps<{
    bookings: {
        data: Booking[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
}>();
const actualStatus = (booking: Booking) =>
    booking.status === 'pending' &&
    !booking.midtrans_order_id &&
    booking.expires_at &&
    new Date(booking.expires_at) <= new Date()
        ? 'expired'
        : booking.status;
</script>
<template>
    <Head title="Reservasi Saya | CINEM4" />
    <div class="astral-theme min-h-screen bg-[#121212] text-white">
        <CinemaNav :transparent="false" />
        <main class="mx-auto max-w-3xl px-5 pt-28 pb-12">
            <Link
                href="/showtimes"
                class="inline-flex min-h-11 items-center gap-2 text-sm text-slate-300 hover:text-white"
                ><ArrowLeft class="size-4" /> Cari jadwal</Link
            >
            <h1 class="mt-8 text-3xl font-bold">Reservasi Saya</h1>
            <p class="mt-3 text-sm text-slate-400">
                Daftar reservasi dan riwayat checkout Midtrans Sandbox.
            </p>
            <div
                v-if="bookings.data.length === 0"
                class="mt-8 rounded-xl border border-slate-700 p-10 text-center"
            >
                <Ticket class="mx-auto size-10 text-amber-400" />
                <p class="mt-4">Belum ada reservasi.</p>
            </div>
            <div v-else class="mt-8 space-y-3">
                <article
                    v-for="booking in bookings.data"
                    :key="booking.id_booking"
                    class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-700 bg-[#171c27] p-5"
                >
                    <div class="min-w-0">
                        <h2 class="font-semibold">
                            {{ booking.schedule.movie.title }}
                        </h2>
                        <p class="mt-2 text-sm text-slate-400">
                            {{ booking.schedule.cinema.name }} ·
                            {{ booking.schedule.show_date.slice(0, 10) }}
                            {{ booking.schedule.show_time.slice(0, 5) }} WIB
                        </p>
                        <p class="mt-2 text-sm text-slate-400">
                            Kursi:
                            {{
                                booking.seats
                                    .map((seat) => seat.seat_number)
                                    .join(', ')
                            }}
                            · {{ actualStatus(booking) }}
                        </p>
                    </div>
                    <Link
                        :href="`/my-bookings/${booking.id_booking}`"
                        class="inline-flex min-h-11 items-center rounded-lg border border-amber-400 px-4 text-sm font-semibold text-amber-300 hover:bg-amber-400/10"
                        >Detail</Link
                    >
                </article>
            </div>
            <nav
                v-if="bookings.prev_page_url || bookings.next_page_url"
                aria-label="Halaman reservasi"
                class="mt-6 flex justify-between gap-4"
            >
                <Link
                    v-if="bookings.prev_page_url"
                    :href="bookings.prev_page_url"
                    class="rounded-lg border border-slate-700 px-4 py-3 text-sm"
                    >Sebelumnya</Link
                ><span v-else />
                <Link
                    v-if="bookings.next_page_url"
                    :href="bookings.next_page_url"
                    class="rounded-lg border border-slate-700 px-4 py-3 text-sm"
                    >Berikutnya</Link
                >
            </nav>
        </main>
    </div>
</template>
