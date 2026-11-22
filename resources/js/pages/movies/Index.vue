<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Clapperboard, Clock3, Film } from '@lucide/vue';

type Movie = {
    id_movie: number;
    title: string;
    slug: string;
    genre: string;
    duration_minute: number;
    status: 'now_showing' | 'coming_soon';
    poster: string | null;
    synopsis: string | null;
    release_date: string | null;
};

defineProps<{
    movies: {
        data: Movie[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
}>();
</script>

<template>
    <Head title="Katalog Film | CINEM4" />

    <div class="min-h-screen bg-[#0c1018] text-white">
        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-12">
            <header
                class="flex flex-wrap items-center justify-between gap-5 border-b border-white/10 pb-7"
            >
                <Link
                    href="/"
                    class="text-2xl font-black tracking-[0.14em] text-white"
                >
                    CINEM<span class="text-amber-400">4</span>
                </Link>
                <nav class="flex items-center gap-5 text-sm text-slate-300">
                    <Link
                        href="/"
                        class="hover:text-white focus-visible:rounded focus-visible:outline-2 focus-visible:outline-amber-400"
                    >
                        Beranda
                    </Link>
                    <Link
                        href="/login"
                        class="rounded-lg border border-white/20 px-4 py-2.5 hover:border-amber-400 hover:text-white focus-visible:outline-2 focus-visible:outline-amber-400"
                    >
                        Masuk
                    </Link>
                </nav>
            </header>

            <section class="py-12 sm:py-16">
                <p
                    class="mb-4 inline-flex items-center gap-2 text-xs font-bold tracking-[0.2em] text-amber-400 uppercase"
                >
                    <Clapperboard class="size-4" />
                    Koleksi film CINEM4
                </p>
                <h1
                    class="max-w-3xl text-4xl font-extrabold tracking-tight sm:text-5xl"
                >
                    Cerita besar dimulai di layar lebar.
                </h1>
                <p
                    class="mt-5 max-w-2xl text-base leading-relaxed text-slate-400"
                >
                    Jelajahi pilihan film yang sudah disiapkan CINEM4. Informasi
                    jadwal dan pemesanan tiket akan tersedia pada tahap
                    berikutnya.
                </p>
            </section>

            <section aria-labelledby="movie-list-heading" class="pb-14">
                <div
                    class="mb-7 flex flex-wrap items-center justify-between gap-4"
                >
                    <h2
                        id="movie-list-heading"
                        class="text-xl font-semibold sm:text-2xl"
                    >
                        Daftar Film
                    </h2>
                    <span class="text-sm text-slate-400">
                        {{ movies.data.length }} film di halaman ini
                    </span>
                </div>

                <div
                    v-if="movies.data.length === 0"
                    class="rounded-2xl border border-white/10 bg-white/5 px-6 py-16 text-center"
                >
                    <Film class="mx-auto size-11 text-amber-400" />
                    <h3 class="mt-5 text-xl font-semibold">Belum ada film</h3>
                    <p class="mx-auto mt-2 max-w-lg text-sm text-slate-400">
                        Katalog masih kosong. Admin dapat menambahkan film
                        melalui fitur impor TMDB.
                    </p>
                </div>

                <div
                    v-else
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <article
                        v-for="movie in movies.data"
                        :key="movie.id_movie"
                        class="group overflow-hidden rounded-xl border border-white/10 bg-[#171c27] transition-colors hover:border-amber-400/50"
                    >
                        <div
                            class="relative aspect-[2/3] overflow-hidden bg-[#222a38]"
                        >
                            <img
                                v-if="movie.poster"
                                :src="movie.poster"
                                :alt="`Poster film ${movie.title}`"
                                loading="lazy"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03] motion-reduce:transition-none"
                            />
                            <div
                                v-else
                                class="flex h-full items-center justify-center text-slate-500"
                            >
                                <Film class="size-12" />
                            </div>
                            <span
                                class="absolute top-3 left-3 rounded-md bg-[#101722]/95 px-3 py-1.5 text-xs font-semibold text-amber-300"
                            >
                                {{
                                    movie.status === 'now_showing'
                                        ? 'Now Showing'
                                        : 'Coming Soon'
                                }}
                            </span>
                        </div>
                        <div class="space-y-3 p-5">
                            <h3 class="line-clamp-2 text-lg font-semibold">
                                {{ movie.title }}
                            </h3>
                            <p class="line-clamp-1 text-sm text-slate-400">
                                {{ movie.genre }}
                            </p>
                            <p
                                class="flex items-center gap-2 text-sm text-slate-400"
                            >
                                <Clock3 class="size-4" />
                                {{
                                    movie.duration_minute > 0
                                        ? `${movie.duration_minute} menit`
                                        : 'Durasi belum tersedia'
                                }}
                            </p>
                            <p
                                class="line-clamp-3 min-h-14 text-sm leading-relaxed text-slate-400"
                            >
                                {{
                                    movie.synopsis ||
                                    'Sinopsis akan segera tersedia.'
                                }}
                            </p>
                        </div>
                    </article>
                </div>

                <div
                    v-if="movies.prev_page_url || movies.next_page_url"
                    class="mt-10 flex items-center justify-between gap-4"
                >
                    <Link
                        v-if="movies.prev_page_url"
                        :href="movies.prev_page_url"
                        class="rounded-lg border border-white/20 px-5 py-3 text-sm hover:border-amber-400 focus-visible:outline-2 focus-visible:outline-amber-400"
                        >Sebelumnya</Link
                    >
                    <span v-else />
                    <Link
                        v-if="movies.next_page_url"
                        :href="movies.next_page_url"
                        class="inline-flex items-center gap-2 rounded-lg bg-amber-400 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-amber-300 focus-visible:outline-2 focus-visible:outline-amber-400"
                    >
                        Berikutnya <ArrowRight class="size-4" />
                    </Link>
                </div>
            </section>
            <footer
                class="border-t border-white/10 py-7 text-xs leading-relaxed text-slate-500"
            >
                Data film disediakan oleh
                <a
                    href="https://www.themoviedb.org/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="underline underline-offset-2"
                    >TMDB</a
                >. This product uses the TMDB API but is not endorsed or
                certified by TMDB.
            </footer>
        </div>
    </div>
</template>
