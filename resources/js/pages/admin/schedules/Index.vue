<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarClock, CircleAlert, Plus, Ticket } from '@lucide/vue';
import { computed, watch } from 'vue';

type Movie = { id_movie: number; title: string; duration_minute: number };
type Cinema = { id_cinema: number; name: string; city: string | null };
type Studio = {
    id_studio: number;
    id_cinema: number;
    name: string;
    seat_capacity: number;
    turnaround_minutes: number;
};
type Schedule = {
    id_schedule: number;
    show_date: string;
    show_time: string;
    price: string;
    seat_capacity: number;
    status: string;
    is_active: boolean;
    movie: { title: string } | null;
    cinema: { name: string } | null;
    studio: { name: string } | null;
    studio_name: string;
};

const props = defineProps<{
    schedules: {
        data: Schedule[];
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    movies: Movie[];
    cinemas: Cinema[];
    studios: Studio[];
    success: string | null;
}>();

const form = useForm({
    id_movie: 0,
    id_cinema: 0,
    id_studio: 0,
    show_date: '',
    show_time: '',
    price: 45000,
});

const availableStudios = computed(() =>
    props.studios.filter((studio) => studio.id_cinema === form.id_cinema),
);
const selectedMovie = computed(() =>
    props.movies.find((movie) => movie.id_movie === form.id_movie),
);
const selectedStudio = computed(() =>
    props.studios.find((studio) => studio.id_studio === form.id_studio),
);

watch(
    () => form.id_cinema,
    () => {
        form.id_studio = 0;
    },
);

function createSchedule(): void {
    form.post('/admin/schedules', {
        preserveScroll: true,
        onSuccess: () => form.reset('show_date', 'show_time'),
    });
}

function cancelSchedule(schedule: Schedule): void {
    if (
        !window.confirm(
            'Batalkan jadwal ini? Pembatalan tidak menghapus data jadwal.',
        )
    ) {
        return;
    }
    router.patch(
        `/admin/schedules/${schedule.id_schedule}/cancel`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Jadwal Tayang | CINEM4" />
    <main class="mx-auto w-full max-w-6xl space-y-8 px-4 py-8 sm:px-8">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-muted-foreground">
                    CINEM4 / Operasional
                </p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">
                    Jadwal Tayang
                </h1>
                <p
                    class="mt-2 max-w-2xl text-sm leading-relaxed text-muted-foreground"
                >
                    Buat jadwal berdasarkan film Now Showing, studio aktif,
                    durasi tayang, dan jeda persiapan.
                </p>
            </div>
            <Link
                href="/admin/cinemas"
                class="inline-flex min-h-11 items-center rounded-lg border px-4 text-sm font-medium hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                Kelola bioskop & studio
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
            class="rounded-xl border bg-card p-5 sm:p-7"
            aria-labelledby="create-schedule-title"
        >
            <div class="mb-6 flex items-center gap-2">
                <CalendarClock class="size-5 text-primary" />
                <h2 id="create-schedule-title" class="text-lg font-semibold">
                    Tambah jadwal baru
                </h2>
            </div>
            <div
                v-if="
                    movies.length === 0 ||
                    cinemas.length === 0 ||
                    studios.length === 0
                "
                class="mb-5 flex items-start gap-3 rounded-lg border border-amber-400/40 bg-amber-400/10 p-4 text-sm"
            >
                <CircleAlert class="mt-0.5 size-5 shrink-0 text-amber-600" />
                <p>
                    Siapkan film aktif berstatus Now Showing, bioskop aktif, dan
                    studio aktif terlebih dahulu.
                </p>
            </div>
            <form
                class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
                @submit.prevent="createSchedule"
            >
                <div class="space-y-2">
                    <label for="schedule-movie" class="text-sm font-medium"
                        >Film</label
                    >
                    <select
                        id="schedule-movie"
                        v-model.number="form.id_movie"
                        required
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <option :value="0" disabled>
                            Pilih film Now Showing
                        </option>
                        <option
                            v-for="movie in movies"
                            :key="movie.id_movie"
                            :value="movie.id_movie"
                        >
                            {{ movie.title }} ({{ movie.duration_minute }} mnt)
                        </option>
                    </select>
                    <p
                        v-if="form.errors.id_movie"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.id_movie }}
                    </p>
                </div>
                <div class="space-y-2">
                    <label for="schedule-cinema" class="text-sm font-medium"
                        >Bioskop</label
                    >
                    <select
                        id="schedule-cinema"
                        v-model.number="form.id_cinema"
                        required
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        <option :value="0" disabled>Pilih lokasi</option>
                        <option
                            v-for="cinema in cinemas"
                            :key="cinema.id_cinema"
                            :value="cinema.id_cinema"
                        >
                            {{ cinema.name }} — {{ cinema.city }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.id_cinema"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.id_cinema }}
                    </p>
                </div>
                <div class="space-y-2">
                    <label for="schedule-studio" class="text-sm font-medium"
                        >Studio</label
                    >
                    <select
                        id="schedule-studio"
                        v-model.number="form.id_studio"
                        required
                        :disabled="form.id_cinema === 0"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50"
                    >
                        <option :value="0" disabled>Pilih studio</option>
                        <option
                            v-for="studio in availableStudios"
                            :key="studio.id_studio"
                            :value="studio.id_studio"
                        >
                            {{ studio.name }} · {{ studio.seat_capacity }} kursi
                        </option>
                    </select>
                    <p
                        v-if="form.errors.id_studio"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.id_studio }}
                    </p>
                </div>
                <div class="space-y-2">
                    <label for="schedule-date" class="text-sm font-medium"
                        >Tanggal tayang (WIB)</label
                    >
                    <input
                        id="schedule-date"
                        v-model="form.show_date"
                        type="date"
                        required
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    />
                    <p
                        v-if="form.errors.show_date"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.show_date }}
                    </p>
                </div>
                <div class="space-y-2">
                    <label for="schedule-time" class="text-sm font-medium"
                        >Jam mulai (WIB)</label
                    >
                    <input
                        id="schedule-time"
                        v-model="form.show_time"
                        type="time"
                        required
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    />
                    <p
                        v-if="form.errors.show_time"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.show_time }}
                    </p>
                </div>
                <div class="space-y-2">
                    <label for="schedule-price" class="text-sm font-medium"
                        >Harga tiket (Rp)</label
                    >
                    <input
                        id="schedule-price"
                        v-model.number="form.price"
                        type="number"
                        required
                        min="1000"
                        max="1000000"
                        step="1000"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    />
                    <p
                        v-if="form.errors.price"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.price }}
                    </p>
                </div>
                <div class="md:col-span-2 xl:col-span-3">
                    <p class="mb-3 text-xs text-muted-foreground">
                        {{
                            selectedMovie && selectedStudio
                                ? `Perkiraan waktu terpakai: ${selectedMovie.duration_minute + selectedStudio.turnaround_minutes} menit, termasuk jeda ${selectedStudio.turnaround_minutes} menit.`
                                : 'Waktu akhir dihitung otomatis dari durasi film dan jeda persiapan studio.'
                        }}
                    </p>
                    <button
                        type="submit"
                        :disabled="
                            form.processing ||
                            movies.length === 0 ||
                            cinemas.length === 0 ||
                            studios.length === 0
                        "
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50"
                    >
                        <Plus class="size-4" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan jadwal' }}
                    </button>
                </div>
            </form>
        </section>

        <section
            aria-labelledby="schedule-list-title"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <div
                class="flex items-center justify-between gap-3 border-b px-5 py-4"
            >
                <h2 id="schedule-list-title" class="font-semibold">
                    Daftar jadwal
                </h2>
                <span class="text-xs text-muted-foreground"
                    >Tanggal dan waktu dalam WIB</span
                >
            </div>
            <div v-if="schedules.data.length === 0" class="p-12 text-center">
                <Ticket class="mx-auto size-10 text-muted-foreground" />
                <h3 class="mt-4 font-semibold">Belum ada jadwal tayang</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Gunakan formulir di atas untuk membuat jadwal pertama.
                </p>
            </div>
            <ul v-else class="divide-y">
                <li
                    v-for="schedule in schedules.data"
                    :key="schedule.id_schedule"
                    class="flex flex-wrap items-center justify-between gap-4 p-5"
                >
                    <div class="min-w-0 space-y-1">
                        <h3 class="font-semibold">
                            {{ schedule.movie?.title || 'Film tidak tersedia' }}
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            {{ schedule.cinema?.name }} ·
                            {{ schedule.studio?.name || schedule.studio_name }}
                        </p>
                        <p class="text-sm">
                            {{ schedule.show_date.slice(0, 10) }} ·
                            {{ schedule.show_time.slice(0, 5) }} WIB · Rp
                            {{ Number(schedule.price).toLocaleString('id-ID') }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ schedule.seat_capacity }} kursi ·
                            {{ schedule.status }} ·
                            {{ schedule.is_active ? 'Aktif' : 'Nonaktif' }}
                        </p>
                    </div>
                    <button
                        v-if="schedule.status !== 'cancelled'"
                        type="button"
                        :aria-label="`Batalkan jadwal ${schedule.movie?.title || schedule.id_schedule}`"
                        class="min-h-11 rounded-lg border px-4 text-sm font-medium text-destructive hover:bg-destructive/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="cancelSchedule(schedule)"
                    >
                        Batalkan jadwal
                    </button>
                </li>
            </ul>
            <nav
                v-if="schedules.prev_page_url || schedules.next_page_url"
                aria-label="Halaman jadwal"
                class="flex justify-between border-t px-5 py-4"
            >
                <Link
                    v-if="schedules.prev_page_url"
                    :href="schedules.prev_page_url"
                    class="min-h-10 rounded-lg border px-4 py-2 text-sm hover:bg-accent"
                    >Sebelumnya</Link
                >
                <span v-else />
                <Link
                    v-if="schedules.next_page_url"
                    :href="schedules.next_page_url"
                    class="min-h-10 rounded-lg border px-4 py-2 text-sm hover:bg-accent"
                    >Berikutnya</Link
                >
            </nav>
        </section>
    </main>
</template>
