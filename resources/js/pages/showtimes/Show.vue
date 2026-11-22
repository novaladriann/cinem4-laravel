<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Armchair, Clock3, Info } from '@lucide/vue';
import { computed } from 'vue';

type Showtime = {
    id_schedule: number;
    show_date: string;
    show_time: string;
    price: string;
    seat_capacity: number;
    studio_name: string;
    booking_close_minutes: number;
    movie: { title: string; duration_minute: number; poster: string | null };
    cinema: { name: string; city: string | null };
};

const props = defineProps<{
    schedule: Showtime;
    occupiedSeats: number[];
    maxSeats: number;
    holdMinutes: number;
}>();

const form = useForm<{ seats: number[] }>({ seats: [] });
const occupied = computed(() => new Set(props.occupiedSeats));
const seats = computed(() =>
    Array.from({ length: props.schedule.seat_capacity }, (_, i) => i + 1),
);
const price = computed(() => Number(props.schedule.price) * form.seats.length);

function toggleSeat(seat: number): void {
    if (occupied.value.has(seat) || form.processing) return;
    if (form.seats.includes(seat)) {
        form.seats = form.seats.filter((number) => number !== seat);
    } else if (form.seats.length < props.maxSeats) {
        form.seats = [...form.seats, seat].sort((a, b) => a - b);
    }
}

function holdSeats(): void {
    form.post(`/showtimes/${props.schedule.id_schedule}/book`, {
        preserveScroll: true,
        onError: () => router.reload({ only: ['occupiedSeats'] }),
    });
}
</script>

<template>
    <Head :title="`Pilih Kursi · ${schedule.movie.title}`" />
    <div class="min-h-screen bg-[#0c1018] text-slate-100">
        <main class="mx-auto max-w-5xl px-5 py-10 sm:px-8">
            <Link
                href="/showtimes"
                class="inline-flex min-h-11 items-center gap-2 text-sm text-slate-300 hover:text-white"
                ><ArrowLeft class="size-4" /> Semua jadwal</Link
            >
            <header class="mt-8">
                <p
                    class="text-xs font-bold tracking-[0.2em] text-amber-400 uppercase"
                >
                    CINEM4 · Pilih kursi
                </p>
                <h1 class="mt-3 text-3xl font-bold tracking-tight">
                    {{ schedule.movie.title }}
                </h1>
                <p class="mt-3 text-sm text-slate-400">
                    {{ schedule.cinema.name }} · {{ schedule.studio_name }} ·
                    {{ schedule.show_date.slice(0, 10) }} ·
                    {{ schedule.show_time.slice(0, 5) }} WIB
                </p>
            </header>
            <div class="mt-9 grid gap-7 lg:grid-cols-[1fr_290px]">
                <section
                    aria-labelledby="seat-heading"
                    class="rounded-2xl border border-slate-700 bg-[#171c27] px-3 py-8 sm:px-7"
                >
                    <h2
                        id="seat-heading"
                        class="text-center text-lg font-semibold"
                    >
                        Pilih tempat duduk
                    </h2>
                    <div class="mx-auto mt-8 max-w-xl">
                        <div
                            class="h-2 rounded-t-[100%] bg-amber-300 shadow-[0_5px_28px_rgba(251,191,36,0.25)]"
                        ></div>
                        <p
                            class="mt-3 text-center text-xs tracking-[0.3em] text-slate-400 uppercase"
                        >
                            Layar bioskop
                        </p>
                        <div
                            class="mt-9 grid grid-cols-8 gap-2 sm:grid-cols-10"
                        >
                            <button
                                v-for="seat in seats"
                                :key="seat"
                                type="button"
                                :disabled="
                                    occupied.has(seat) ||
                                    (form.seats.length >= maxSeats &&
                                        !form.seats.includes(seat)) ||
                                    form.processing
                                "
                                :aria-label="`Kursi ${seat}: ${occupied.has(seat) ? 'tidak tersedia' : form.seats.includes(seat) ? 'dipilih' : 'tersedia'}`"
                                :aria-pressed="form.seats.includes(seat)"
                                :class="[
                                    'flex aspect-square min-h-9 items-center justify-center rounded-md border text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400',
                                    occupied.has(seat)
                                        ? 'cursor-not-allowed border-slate-700 bg-slate-700 text-slate-400'
                                        : form.seats.includes(seat)
                                          ? 'border-amber-300 bg-amber-400 text-slate-950'
                                          : 'border-slate-500 bg-[#273042] text-slate-100 hover:border-amber-400',
                                ]"
                                @click="toggleSeat(seat)"
                            >
                                {{ seat }}
                            </button>
                        </div>
                        <div
                            class="mt-8 flex flex-wrap items-center justify-center gap-5 text-xs text-slate-300"
                        >
                            <span class="inline-flex items-center gap-2"
                                ><span
                                    class="size-4 rounded border border-slate-500 bg-[#273042]"
                                ></span>
                                Tersedia</span
                            >
                            <span class="inline-flex items-center gap-2"
                                ><span
                                    class="size-4 rounded bg-amber-400"
                                ></span>
                                Dipilih</span
                            >
                            <span class="inline-flex items-center gap-2"
                                ><span
                                    class="size-4 rounded bg-slate-700"
                                ></span>
                                Tidak tersedia</span
                            >
                        </div>
                    </div>
                </section>
                <aside
                    class="h-fit space-y-5 rounded-2xl border border-slate-700 bg-[#171c27] p-5"
                >
                    <div class="flex items-center gap-2">
                        <Armchair class="size-5 text-amber-400" />
                        <h2 class="font-semibold">Ringkasan reservasi</h2>
                    </div>
                    <p class="text-sm text-slate-400">Kursi terpilih</p>
                    <p class="text-xl font-semibold">
                        {{
                            form.seats.length
                                ? form.seats.join(', ')
                                : 'Belum dipilih'
                        }}
                    </p>
                    <p class="text-sm text-slate-400">
                        Maksimal {{ maxSeats }} kursi per reservasi
                    </p>
                    <div class="border-t border-slate-700 pt-4">
                        <p class="text-sm text-slate-400">Total harga</p>
                        <p class="mt-1 text-2xl font-bold text-amber-300">
                            Rp {{ price.toLocaleString('id-ID') }}
                        </p>
                    </div>
                    <div
                        class="flex items-start gap-2 rounded-lg bg-slate-800 p-3 text-xs leading-relaxed text-slate-300"
                    >
                        <Info class="size-4 shrink-0 text-amber-400" /> Kursi
                        ditahan {{ holdMinutes }} menit setelah reservasi.
                        Pembayaran Midtrans belum tersedia.
                    </div>
                    <p
                        v-if="form.errors.seats"
                        role="alert"
                        class="text-sm text-red-300"
                    >
                        {{ form.errors.seats }}
                    </p>
                    <p
                        v-if="form.errors['seats.0']"
                        role="alert"
                        class="text-sm text-red-300"
                    >
                        {{ form.errors['seats.0'] }}
                    </p>
                    <button
                        type="button"
                        :disabled="form.seats.length === 0 || form.processing"
                        class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-amber-400 px-5 text-sm font-semibold text-slate-950 hover:bg-amber-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="holdSeats"
                    >
                        <Clock3 class="size-4" />{{
                            form.processing
                                ? 'Memproses...'
                                : 'Tahan kursi sementara'
                        }}
                    </button>
                </aside>
            </div>
        </main>
    </div>
</template>
