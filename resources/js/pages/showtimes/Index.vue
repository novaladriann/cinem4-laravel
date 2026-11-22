<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    Clapperboard,
    Clock3,
    MapPin,
} from '@lucide/vue';

type Showtime = {
    id_schedule: number;
    show_date: string;
    show_time: string;
    price: string;
    seat_capacity: number;
    studio_name: string;
    movie: { title: string; poster: string | null; duration_minute: number };
    cinema: { name: string; city: string | null };
};

defineProps<{ schedules: Showtime[] }>();

const money = (value: string) => `Rp ${Number(value).toLocaleString('id-ID')}`;
</script>

<template>
    <Head title="Jadwal Tayang | CINEM4" />
    <div class="min-h-screen bg-[#0c1018] text-slate-100">
        <main class="mx-auto max-w-6xl px-5 py-10 sm:px-8">
            <nav class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/movies"
                    class="text-xl font-black tracking-widest text-white"
                    >CINEM<span class="text-amber-400">4</span></Link
                >
                <Link
                    href="/my-bookings"
                    class="rounded-lg border border-slate-700 px-4 py-2 text-sm hover:border-amber-400"
                    >Reservasi Saya</Link
                >
            </nav>
            <section class="mt-14 mb-9">
                <p
                    class="flex items-center gap-2 text-sm font-semibold tracking-widest text-amber-400 uppercase"
                >
                    <Clapperboard class="size-4" /> Jadwal aktif
                </p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-5xl">
                    Pilih jadwal tayang
                </h1>
                <p class="mt-4 max-w-2xl text-slate-400">
                    Pilih pertunjukan, lihat kursi yang tersedia, dan buat
                    reservasi. Pembayaran belum diaktifkan.
                </p>
            </section>

            <div
                v-if="schedules.length === 0"
                class="rounded-2xl border border-slate-700 p-12 text-center"
            >
                <CalendarDays class="mx-auto size-10 text-amber-400" />
                <h2 class="mt-4 text-lg font-semibold">
                    Belum ada jadwal yang tersedia
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    Silakan periksa kembali saat jadwal tayang telah
                    ditambahkan.
                </p>
            </div>

            <div v-else class="grid gap-5 md:grid-cols-2">
                <article
                    v-for="show in schedules"
                    :key="show.id_schedule"
                    class="flex gap-4 rounded-2xl border border-slate-700/80 bg-[#171c27] p-4 sm:p-5"
                >
                    <img
                        v-if="show.movie.poster"
                        :src="show.movie.poster"
                        :alt="`Poster ${show.movie.title}`"
                        loading="lazy"
                        class="h-40 w-27 shrink-0 rounded-lg bg-slate-700 object-cover"
                    />
                    <div
                        v-else
                        class="flex h-40 w-27 shrink-0 items-center justify-center rounded-lg bg-slate-800"
                    >
                        <Clapperboard class="size-9 text-slate-500" />
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                        <h2 class="line-clamp-2 text-lg font-semibold">
                            {{ show.movie.title }}
                        </h2>
                        <p
                            class="flex items-center gap-2 text-sm text-slate-400"
                        >
                            <MapPin class="size-4 shrink-0" />{{
                                show.cinema.name
                            }}
                            · {{ show.studio_name }}
                        </p>
                        <p
                            class="flex items-center gap-2 text-sm text-slate-300"
                        >
                            <Clock3 class="size-4 shrink-0" />{{
                                show.show_date.slice(0, 10)
                            }}
                            · {{ show.show_time.slice(0, 5) }} WIB
                        </p>
                        <p class="text-sm font-semibold text-amber-300">
                            {{ money(show.price) }}
                            <span class="font-normal text-slate-400"
                                >/ kursi</span
                            >
                        </p>
                        <Link
                            :href="`/showtimes/${show.id_schedule}`"
                            class="mt-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                        >
                            Pilih kursi <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </article>
            </div>
        </main>
    </div>
</template>
