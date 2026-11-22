<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, Clapperboard, Plus, Ticket } from '@lucide/vue';

type Studio = {
    id_studio: number;
    name: string;
    seat_capacity: number;
    turnaround_minutes: number;
    is_active: boolean;
};

type Cinema = {
    id_cinema: number;
    name: string;
    city: string | null;
    address: string | null;
    is_active: boolean;
    studios: Studio[];
};

defineProps<{ cinemas: Cinema[]; success: string | null }>();

const cinemaForm = useForm({ name: '', city: '', address: '' });
const studioForm = useForm({
    id_cinema: 0,
    name: '',
    seat_capacity: 40,
    turnaround_minutes: 15,
});

function addCinema(): void {
    cinemaForm.post('/admin/cinemas', {
        preserveScroll: true,
        onSuccess: () => cinemaForm.reset(),
    });
}

function addStudio(): void {
    studioForm.post('/admin/studios', {
        preserveScroll: true,
        onSuccess: () => studioForm.reset('name'),
    });
}

function toggleCinema(cinema: Cinema): void {
    router.patch(
        `/admin/cinemas/${cinema.id_cinema}`,
        { is_active: !cinema.is_active },
        { preserveScroll: true },
    );
}

function toggleStudio(studio: Studio): void {
    router.patch(
        `/admin/studios/${studio.id_studio}`,
        { is_active: !studio.is_active },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Bioskop dan Studio | CINEM4" />

    <main class="mx-auto w-full max-w-6xl space-y-8 px-4 py-8 sm:px-8">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <p class="text-sm font-medium text-muted-foreground">CINEM4 / Operasional</p>
                <h1 class="text-3xl font-bold tracking-tight">Bioskop & Studio</h1>
                <p class="max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    Daftarkan lokasi bioskop dan ruang tayang. Kapasitas kursi menjadi acuan saat membuat jadwal baru.
                </p>
            </div>
            <Link
                href="/admin/schedules"
                class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-4 text-sm font-medium hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <Ticket class="size-4" /> Kelola jadwal
            </Link>
        </header>

        <div
            v-if="success"
            role="status"
            class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-300"
        >
            {{ success }}
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <form class="space-y-4 rounded-xl border bg-card p-5" @submit.prevent="addCinema">
                <div class="flex items-center gap-2">
                    <Building2 class="size-5 text-primary" />
                    <h2 class="text-lg font-semibold">Tambah bioskop</h2>
                </div>
                <div class="space-y-2">
                    <label for="cinema-name" class="text-sm font-medium">Nama bioskop</label>
                    <input id="cinema-name" v-model="cinemaForm.name" required maxlength="100" placeholder="CINEM4 Cirebon"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" />
                    <p v-if="cinemaForm.errors.name" role="alert" class="text-sm text-destructive">{{ cinemaForm.errors.name }}</p>
                </div>
                <div class="space-y-2">
                    <label for="cinema-city" class="text-sm font-medium">Kota</label>
                    <input id="cinema-city" v-model="cinemaForm.city" required maxlength="100" placeholder="Cirebon"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" />
                    <p v-if="cinemaForm.errors.city" role="alert" class="text-sm text-destructive">{{ cinemaForm.errors.city }}</p>
                </div>
                <div class="space-y-2">
                    <label for="cinema-address" class="text-sm font-medium">Alamat (opsional)</label>
                    <textarea id="cinema-address" v-model="cinemaForm.address" rows="2" maxlength="1000"
                        class="w-full rounded-lg border border-input bg-background p-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" />
                    <p v-if="cinemaForm.errors.address" role="alert" class="text-sm text-destructive">{{ cinemaForm.errors.address }}</p>
                </div>
                <button type="submit" :disabled="cinemaForm.processing"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50">
                    <Plus class="size-4" /> {{ cinemaForm.processing ? 'Menyimpan...' : 'Tambah bioskop' }}
                </button>
            </form>

            <form class="space-y-4 rounded-xl border bg-card p-5" @submit.prevent="addStudio">
                <div class="flex items-center gap-2">
                    <Clapperboard class="size-5 text-primary" />
                    <h2 class="text-lg font-semibold">Tambah studio</h2>
                </div>
                <div class="space-y-2">
                    <label for="studio-cinema" class="text-sm font-medium">Bioskop</label>
                    <select id="studio-cinema" v-model.number="studioForm.id_cinema" required
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                        <option :value="0" disabled>Pilih bioskop aktif</option>
                        <option v-for="cinema in cinemas.filter((item) => item.is_active)" :key="cinema.id_cinema" :value="cinema.id_cinema">
                            {{ cinema.name }} — {{ cinema.city || 'Tanpa kota' }}
                        </option>
                    </select>
                    <p v-if="studioForm.errors.id_cinema" role="alert" class="text-sm text-destructive">{{ studioForm.errors.id_cinema }}</p>
                </div>
                <div class="space-y-2">
                    <label for="studio-name" class="text-sm font-medium">Nama ruang</label>
                    <input id="studio-name" v-model="studioForm.name" required maxlength="50" placeholder="Studio 1"
                        class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" />
                    <p v-if="studioForm.errors.name" role="alert" class="text-sm text-destructive">{{ studioForm.errors.name }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-2">
                        <label for="seat-capacity" class="text-sm font-medium">Kapasitas kursi</label>
                        <input id="seat-capacity" v-model.number="studioForm.seat_capacity" type="number" required min="1" max="300"
                            class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" />
                        <p v-if="studioForm.errors.seat_capacity" role="alert" class="text-sm text-destructive">{{ studioForm.errors.seat_capacity }}</p>
                    </div>
                    <div class="space-y-2">
                        <label for="turnaround" class="text-sm font-medium">Jeda (menit)</label>
                        <input id="turnaround" v-model.number="studioForm.turnaround_minutes" type="number" required min="5" max="90"
                            class="h-11 w-full rounded-lg border border-input bg-background px-3 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" />
                        <p v-if="studioForm.errors.turnaround_minutes" role="alert" class="text-sm text-destructive">{{ studioForm.errors.turnaround_minutes }}</p>
                    </div>
                </div>
                <p class="text-xs leading-relaxed text-muted-foreground">Jeda studio dihitung setelah film selesai agar dua jadwal tidak terlalu berdekatan.</p>
                <button type="submit" :disabled="studioForm.processing || cinemas.every((item) => !item.is_active)"
                    class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-semibold text-primary-foreground hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50">
                    <Plus class="size-4" /> {{ studioForm.processing ? 'Menyimpan...' : 'Tambah studio' }}
                </button>
            </form>
        </div>

        <section aria-labelledby="cinema-list-title" class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h2 id="cinema-list-title" class="text-xl font-semibold">Daftar lokasi</h2>
                <span class="text-sm text-muted-foreground">{{ cinemas.length }} bioskop</span>
            </div>
            <div v-if="cinemas.length === 0" class="rounded-xl border border-dashed p-12 text-center">
                <Building2 class="mx-auto size-10 text-muted-foreground" />
                <h3 class="mt-4 font-semibold">Belum ada bioskop</h3>
                <p class="mt-2 text-sm text-muted-foreground">Tambah lokasi pertama melalui formulir di atas.</p>
            </div>
            <article v-for="cinema in cinemas" :key="cinema.id_cinema" class="rounded-xl border bg-card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4">
                    <div>
                        <h3 class="font-semibold">{{ cinema.name }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ cinema.city || 'Kota belum diisi' }}<span v-if="cinema.address"> · {{ cinema.address }}</span></p>
                    </div>
                    <button type="button" :aria-label="`${cinema.is_active ? 'Nonaktifkan' : 'Aktifkan'} bioskop ${cinema.name}`"
                        class="min-h-11 rounded-lg border px-4 text-sm font-medium hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        @click="toggleCinema(cinema)">
                        {{ cinema.is_active ? 'Nonaktifkan bioskop' : 'Aktifkan bioskop' }}
                    </button>
                </div>
                <div class="space-y-3 p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {{ cinema.is_active ? 'Bioskop aktif' : 'Bioskop nonaktif' }} · {{ cinema.studios.length }} studio
                    </p>
                    <p v-if="cinema.studios.length === 0" class="text-sm text-muted-foreground">Belum ada studio di lokasi ini.</p>
                    <div v-for="studio in cinema.studios" :key="studio.id_studio" class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/50 px-4 py-3">
                        <div>
                            <p class="text-sm font-semibold">{{ studio.name }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ studio.seat_capacity }} kursi · Jeda {{ studio.turnaround_minutes }} menit · {{ studio.is_active ? 'Aktif' : 'Nonaktif' }}
                            </p>
                        </div>
                        <button type="button"
                            :aria-label="`${studio.is_active ? 'Nonaktifkan' : 'Aktifkan'} ${studio.name} di ${cinema.name}`"
                            class="min-h-10 rounded-lg border bg-background px-3 text-xs font-medium hover:bg-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                            @click="toggleStudio(studio)">
                            {{ studio.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </div>
                </div>
            </article>
        </section>
    </main>
</template>
