<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CheckCircle2, ShieldCheck } from '@lucide/vue';
import { computed } from 'vue';

type Ticket = {
    code: string;
    used_at: string | null;
    needs_review: boolean;
    booking: { id_booking: number; status: string; seat_count: number };
    movie: string;
    cinema: string;
    studio: string;
    date: string;
    time: string;
    seats: number[];
};

const props = defineProps<{ ticket: Ticket }>();
const page = usePage();
const errors = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.ticket,
);

function redeem(): void {
    if (
        window.confirm(
            'Konfirmasi tiket ini telah digunakan untuk masuk studio?',
        )
    ) {
        router.post(`/admin/tickets/${props.ticket.code}/redeem`);
    }
}
</script>

<template>
    <Head title="Verifikasi Tiket | CINEM4" />
    <main class="mx-auto max-w-xl space-y-6 px-5 py-10">
        <Link
            href="/admin/schedules"
            class="text-sm underline underline-offset-4"
            >Kembali ke jadwal</Link
        >
        <div class="rounded-xl border bg-card p-7">
            <ShieldCheck class="size-8 text-primary" />
            <h1 class="mt-4 text-2xl font-bold">Verifikasi tiket CINEM4</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Hanya petugas admin yang dapat membuka dan menggunakan tiket.
            </p>
            <dl class="mt-6 grid gap-3 text-sm">
                <div>
                    <dt class="text-muted-foreground">Film</dt>
                    <dd class="font-semibold">{{ ticket.movie }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Lokasi</dt>
                    <dd>{{ ticket.cinema }} · {{ ticket.studio }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Jadwal</dt>
                    <dd>
                        {{ ticket.date.slice(0, 10) }} ·
                        {{ ticket.time.slice(0, 5) }} WIB
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Kursi</dt>
                    <dd>{{ ticket.seats.join(', ') }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Status</dt>
                    <dd>
                        {{
                            ticket.needs_review
                                ? 'Perlu pemeriksaan pembayaran'
                                : ticket.used_at
                                  ? 'Sudah digunakan'
                                  : ticket.booking.status === 'paid'
                                    ? 'Valid dan belum digunakan'
                                    : 'Tidak valid'
                        }}
                    </dd>
                </div>
            </dl>
            <p v-if="errors" role="alert" class="mt-5 text-sm text-destructive">
                {{ errors }}
            </p>
            <button
                v-if="
                    ticket.booking.status === 'paid' &&
                    !ticket.used_at &&
                    !ticket.needs_review
                "
                type="button"
                class="mt-6 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 font-semibold text-primary-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="redeem"
            >
                <CheckCircle2 class="size-4" /> Tandai tiket digunakan
            </button>
        </div>
    </main>
</template>
