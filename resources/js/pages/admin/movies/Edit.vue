<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Film, Star } from '@lucide/vue';

type Movie = {
    id_movie: number;
    title: string;
    poster: string | null;
    tmdb_id: number | null;
    genre: string;
    status: 'now_showing' | 'coming_soon';
    is_active: boolean;
    is_featured: boolean;
};

const props = defineProps<{ movie: Movie }>();

const form = useForm({
    status: props.movie.status,
    is_active: props.movie.is_active,
    is_featured: props.movie.is_featured,
});

function save(): void {
    form.patch(`/admin/movies/${props.movie.id_movie}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Atur film: ${movie.title}`" />

    <main class="mx-auto w-full max-w-3xl space-y-7 px-4 py-8 sm:px-8">
        <Link
            href="/admin/movies"
            class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
            <ArrowLeft class="size-4" /> Kembali ke kelola film
        </Link>

        <header class="flex gap-5">
            <img
                v-if="movie.poster"
                :src="movie.poster"
                :alt="`Poster ${movie.title}`"
                class="h-32 w-22 shrink-0 rounded-lg bg-muted object-cover"
            />
            <div
                v-else
                class="flex h-32 w-22 shrink-0 items-center justify-center rounded-lg bg-muted"
            >
                <Film class="size-7 text-muted-foreground" />
            </div>
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">Pengaturan film</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ movie.title }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ movie.genre
                    }}<span v-if="movie.tmdb_id">
                        · TMDB #{{ movie.tmdb_id }}</span
                    >
                </p>
            </div>
        </header>

        <form
            class="space-y-6 rounded-xl border bg-card p-5 sm:p-7"
            @submit.prevent="save"
        >
            <div class="space-y-2">
                <label for="status" class="text-sm font-semibold"
                    >Status tayang</label
                >
                <p class="text-sm text-muted-foreground">
                    Status ini adalah penanda katalog. Menjadi Now Showing belum
                    otomatis membuat jadwal tayang.
                </p>
                <select
                    id="status"
                    v-model="form.status"
                    :aria-invalid="Boolean(form.errors.status)"
                    class="mt-2 h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <option value="coming_soon">Coming Soon</option>
                    <option value="now_showing">Now Showing</option>
                </select>
                <p
                    v-if="form.errors.status"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.status }}
                </p>
            </div>

            <div class="space-y-4 border-t pt-5">
                <label
                    class="flex cursor-pointer items-start gap-4 rounded-lg border px-4 py-4 hover:bg-accent/30"
                >
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="mt-1 size-5 shrink-0 accent-primary"
                        :aria-invalid="Boolean(form.errors.is_active)"
                    />
                    <span class="flex-1">
                        <span class="block text-sm font-semibold"
                            >Tampilkan di katalog publik</span
                        >
                        <span
                            class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                        >
                            Jika nonaktif, film disembunyikan dari katalog tanpa
                            menghapus datanya.
                        </span>
                        <span
                            v-if="form.errors.is_active"
                            role="alert"
                            class="mt-2 block text-sm text-destructive"
                            >{{ form.errors.is_active }}</span
                        >
                    </span>
                </label>
                <label
                    class="flex cursor-pointer items-start gap-4 rounded-lg border px-4 py-4 hover:bg-accent/30"
                >
                    <input
                        v-model="form.is_featured"
                        type="checkbox"
                        class="mt-1 size-5 shrink-0 accent-primary"
                        :aria-invalid="Boolean(form.errors.is_featured)"
                    />
                    <span class="flex-1">
                        <span
                            class="flex items-center gap-2 text-sm font-semibold"
                        >
                            <Star class="size-4 text-amber-500" /> Film unggulan
                        </span>
                        <span
                            class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                        >
                            Film unggulan aktif ditampilkan lebih dahulu pada
                            katalog publik.
                        </span>
                        <span
                            v-if="form.errors.is_featured"
                            role="alert"
                            class="mt-2 block text-sm text-destructive"
                            >{{ form.errors.is_featured }}</span
                        >
                    </span>
                </label>
            </div>

            <div
                class="flex flex-wrap items-center justify-end gap-3 border-t pt-6"
            >
                <Link
                    href="/admin/movies"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border px-5 text-sm font-medium hover:bg-accent"
                >
                    Batal
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan perubahan' }}
                </button>
            </div>
        </form>
    </main>
</template>
