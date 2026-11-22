<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowUpRight, Film, Plus, Star } from '@lucide/vue';

type Movie = {
    id_movie: number;
    title: string;
    genre: string;
    poster: string | null;
    status: 'now_showing' | 'coming_soon';
    is_active: boolean;
    is_featured: boolean;
    tmdb_id: number | null;
};

defineProps<{
    movies: {
        data: Movie[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    success: string | null;
}>();
</script>

<template>
    <Head title="Kelola Film" />

    <main class="mx-auto w-full max-w-6xl space-y-7 px-4 py-8 sm:px-8">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-muted-foreground">
                    CINEM4 / Admin
                </p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">
                    Kelola Film
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                >
                    Atur film yang tampil di katalog, status tayang, serta
                    pilihan unggulan.
                </p>
            </div>
            <Link
                href="/admin/movies/import"
                class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <Plus class="size-4" /> Impor dari TMDB
            </Link>
        </header>

        <div
            v-if="success"
            role="status"
            class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300"
        >
            {{ success }}
        </div>

        <section
            aria-label="Film yang sudah diimpor"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <div class="border-b px-5 py-4">
                <h2 class="font-semibold">Daftar film</h2>
                <p class="text-sm text-muted-foreground">
                    Film nonaktif tetap tersimpan tetapi tidak tampil untuk
                    pelanggan.
                </p>
            </div>

            <div v-if="movies.data.length === 0" class="px-5 py-16 text-center">
                <Film class="mx-auto size-11 text-muted-foreground" />
                <h3 class="mt-4 font-semibold">Belum ada film di CINEM4</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Mulai dengan mengimpor metadata dari TMDB.
                </p>
                <Link
                    href="/admin/movies/import"
                    class="mt-5 inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-accent"
                >
                    Cari film <ArrowUpRight class="size-4" />
                </Link>
            </div>

            <ul v-else class="divide-y">
                <li
                    v-for="movie in movies.data"
                    :key="movie.id_movie"
                    class="flex flex-wrap items-center gap-4 p-4 sm:flex-nowrap sm:p-5"
                >
                    <img
                        v-if="movie.poster"
                        :src="movie.poster"
                        :alt="`Poster ${movie.title}`"
                        loading="lazy"
                        class="h-20 w-14 shrink-0 rounded-md bg-muted object-cover"
                    />
                    <div
                        v-else
                        class="flex h-20 w-14 shrink-0 items-center justify-center rounded-md bg-muted"
                    >
                        <Film class="size-5 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="line-clamp-2 font-semibold">
                            {{ movie.title }}
                        </h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ movie.genre || 'Genre belum tersedia' }}
                        </p>
                        <div
                            class="mt-2 flex flex-wrap items-center gap-2 text-xs"
                        >
                            <span class="rounded-md border px-2 py-1">
                                {{
                                    movie.status === 'now_showing'
                                        ? 'Now Showing'
                                        : 'Coming Soon'
                                }}
                            </span>
                            <span
                                :class="
                                    movie.is_active
                                        ? 'text-emerald-700 dark:text-emerald-400'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ movie.is_active ? '● Aktif' : '○ Nonaktif' }}
                            </span>
                            <span
                                v-if="movie.is_featured"
                                class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400"
                            >
                                <Star class="size-3" /> Unggulan
                            </span>
                        </div>
                    </div>
                    <Link
                        :href="`/admin/movies/${movie.id_movie}/edit`"
                        :aria-label="`Atur film ${movie.title}`"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium transition hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        Atur film <ArrowUpRight class="size-4" />
                    </Link>
                </li>
            </ul>

            <nav
                v-if="movies.prev_page_url || movies.next_page_url"
                aria-label="Halaman daftar film"
                class="flex items-center justify-between gap-4 border-t px-5 py-4 text-sm"
            >
                <Link
                    v-if="movies.prev_page_url"
                    :href="movies.prev_page_url"
                    class="rounded-lg border px-4 py-2 hover:bg-accent"
                    >Sebelumnya</Link
                >
                <span v-else />
                <Link
                    v-if="movies.next_page_url"
                    :href="movies.next_page_url"
                    class="rounded-lg border px-4 py-2 hover:bg-accent"
                    >Berikutnya</Link
                >
            </nav>
        </section>
    </main>
</template>
