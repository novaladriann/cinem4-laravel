<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Film, Search, Sparkles } from '@lucide/vue';
import { ref } from 'vue';

type SearchResult = {
    id: number;
    title: string;
    release_date: string;
    overview: string;
    poster: string | null;
};

const props = defineProps<{
    query: string;
    results: SearchResult[];
    importedIds: number[];
    tmdbError: string | null;
    success: string | null;
    errors?: Record<string, string>;
}>();

const term = ref(props.query);
const busy = ref(false);
const importingId = ref<number | null>(null);

function search(): void {
    if (term.value.trim().length < 2 || term.value.trim().length > 80) {
        return;
    }

    busy.value = true;
    router.get(
        '/admin/movies/import',
        { q: term.value.trim() },
        {
            preserveState: true,
            replace: true,
            onFinish: () => {
                busy.value = false;
            },
        },
    );
}

function importMovie(id: number): void {
    importingId.value = id;
    router.post(
        `/admin/movies/import/${id}`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                importingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Impor film dari TMDB" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-8 sm:px-8">
        <div class="space-y-3">
            <Link
                href="/movies"
                class="inline-flex items-center gap-2 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
            >
                <ArrowLeft class="size-4" />
                Lihat katalog
            </Link>
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-primary/10 p-3 text-primary">
                    <Film class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        Impor film dari TMDB
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Cari film, tinjau hasil, lalu masukkan ke katalog
                        CINEM4.
                    </p>
                </div>
            </div>
        </div>

        <form
            class="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-end"
            role="search"
            @submit.prevent="search"
        >
            <div class="flex-1 space-y-2">
                <label for="film-query" class="text-sm font-medium"
                    >Judul film</label
                >
                <input
                    id="film-query"
                    v-model="term"
                    type="search"
                    name="q"
                    required
                    minlength="2"
                    maxlength="80"
                    autocomplete="off"
                    placeholder="Contoh: Dune, Interstellar..."
                    class="h-11 w-full rounded-lg border border-input bg-background px-4 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                />
            </div>
            <button
                type="submit"
                :disabled="
                    busy || term.trim().length < 2 || term.trim().length > 80
                "
                class="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-primary px-5 text-sm font-medium text-primary-foreground transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50"
            >
                <Search class="size-4" />
                {{ busy ? 'Mencari...' : 'Cari film' }}
            </button>
        </form>

        <div
            v-if="success"
            role="status"
            class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-300"
        >
            {{ success }}
        </div>
        <div
            v-if="tmdbError || errors?.tmdb"
            role="alert"
            class="rounded-lg border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
        >
            {{ tmdbError || errors?.tmdb }}
        </div>

        <div
            v-if="!query"
            class="rounded-xl border border-dashed px-6 py-14 text-center"
        >
            <Sparkles class="mx-auto mb-4 size-9 text-muted-foreground" />
            <h2 class="text-lg font-semibold">Mulai dari judul film</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Cari melalui TMDB. Film yang diimpor akan berstatus Coming Soon.
            </p>
        </div>

        <div v-else class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">
                    Hasil pencarian untuk “{{ query }}”
                </h2>
                <span class="text-sm text-muted-foreground">
                    {{ results.length }} hasil
                </span>
            </div>

            <div
                v-if="results.length === 0 && !tmdbError"
                class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
            >
                Tidak ditemukan film yang cocok. Coba judul lainnya.
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="movie in results"
                    :key="movie.id"
                    class="flex min-w-0 gap-4 rounded-xl border bg-card p-4"
                >
                    <img
                        v-if="movie.poster"
                        :src="movie.poster"
                        :alt="`Poster ${movie.title}`"
                        loading="lazy"
                        class="h-36 w-24 shrink-0 rounded-lg bg-muted object-cover"
                    />
                    <div
                        v-else
                        class="flex h-36 w-24 shrink-0 items-center justify-center rounded-lg bg-muted text-xs text-muted-foreground"
                    >
                        Tanpa poster
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <h3 class="line-clamp-2 font-semibold">
                            {{ movie.title }}
                        </h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                movie.release_date?.slice(0, 4) ||
                                'Tahun tidak tersedia'
                            }}
                        </p>
                        <p
                            class="mt-2 line-clamp-3 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ movie.overview || 'Sinopsis belum tersedia.' }}
                        </p>
                        <button
                            type="button"
                            :disabled="
                                importingId !== null ||
                                importedIds.includes(movie.id)
                            "
                            class="mt-auto inline-flex min-h-10 items-center justify-center rounded-lg border border-primary bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground transition hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:border-border disabled:bg-muted disabled:text-muted-foreground"
                            @click="importMovie(movie.id)"
                        >
                            {{
                                importedIds.includes(movie.id)
                                    ? 'Sudah diimpor'
                                    : importingId === movie.id
                                      ? 'Mengimpor...'
                                      : 'Impor film'
                            }}
                        </button>
                    </div>
                </article>
            </div>
        </div>

        <p class="text-xs leading-relaxed text-muted-foreground">
            Data film disediakan oleh
            <a
                href="https://www.themoviedb.org/"
                target="_blank"
                rel="noopener noreferrer"
                class="font-medium underline underline-offset-2"
                >TMDB</a
            >. This product uses the TMDB API but is not endorsed or certified
            by TMDB.
        </p>
    </div>
</template>
