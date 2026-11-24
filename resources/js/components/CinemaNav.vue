<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    Clapperboard,
    LogOut,
    MapPin,
    Menu,
    Search,
    Settings,
    ShieldCheck,
    Ticket,
    User,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type CinemaOption = {
    id: string;
    label: string;
    city?: string | null;
};

const props = withDefaults(
    defineProps<{
        cinemas?: CinemaOption[];
        selectedCinema?: string;
        activeCinemaDisplay?: string | null;
        searchQuery?: string;
        showSearch?: boolean;
        transparent?: boolean;
    }>(),
    {
        cinemas: () => [],
        selectedCinema: 'all',
        activeCinemaDisplay: null,
        searchQuery: '',
        showSearch: false,
        transparent: false,
    },
);

const emit = defineEmits<{
    (e: 'update:selectedCinema', value: string): void;
    (e: 'update:searchQuery', value: string): void;
    (e: 'logoClick', event: MouseEvent): void;
}>();

const page = usePage();
const user = computed(
    () =>
        (
            page.props.auth as {
                user?: {
                    name: string;
                    email: string;
                    is_admin?: boolean;
                } | null;
            }
        )?.user ?? null,
);

const cinemaMenuOpen = ref(false);
const userMenuOpen = ref(false);
const searchOpen = ref(false);
const mobileMenuOpen = ref(false);
const isScrolled = ref(false);

const activeCinemaLabel = computed(() => {
    if (props.selectedCinema === 'all') {
        return props.activeCinemaDisplay || 'Semua Bioskop';
    }
    return (
        props.cinemas.find((c) => c.id === props.selectedCinema)?.label ??
        props.activeCinemaDisplay ??
        'Semua Bioskop'
    );
});

const userInitials = computed(() => {
    if (!user.value?.name) return 'U';
    return user.value.name
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
});

function selectCinema(id: string): void {
    emit('update:selectedCinema', id);
    cinemaMenuOpen.value = false;
}

function toggleSearch(): void {
    searchOpen.value = !searchOpen.value;
    if (!searchOpen.value) {
        emit('update:searchQuery', '');
    }
}

function handleScroll(): void {
    if (typeof window === 'undefined') return;
    isScrolled.value = window.scrollY >= 20;
}

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <header
        :class="[
            'fixed inset-x-0 top-0 z-50 w-full transition-colors duration-300',
            transparent && !isScrolled && !mobileMenuOpen
                ? 'border-b border-transparent bg-gradient-to-b from-[#121212]/85 via-[#121212]/40 to-transparent'
                : 'border-b border-white/10 bg-[#121212]/90 backdrop-blur-md',
        ]"
    >
        <div
            class="mx-auto flex h-20 max-w-[1400px] items-center justify-between gap-4 px-5 sm:px-8 lg:px-12"
        >
            <!-- Brand + All Movies Pill -->
            <div class="flex items-center gap-5 sm:gap-7">
                <Link
                    href="/showtimes"
                    class="group inline-flex items-center gap-1 font-display text-2xl font-extrabold tracking-tight text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#f5b82e]"
                    @click="emit('logoClick', $event)"
                >
                    <span>CINEM</span>
                    <span
                        class="text-[#f5b82e] transition-transform duration-300 group-hover:scale-110"
                        >4</span
                    >
                </Link>

                <Link
                    href="/movies"
                    class="hidden min-h-9 items-center gap-2 rounded-full border border-white/25 bg-white/5 px-4 py-1.5 text-xs font-medium text-white/90 transition hover:border-[#f5b82e] hover:bg-[#f5b82e]/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e] sm:inline-flex"
                >
                    <Clapperboard class="size-3.5 text-[#f5b82e]" />
                    <span>Semua Film</span>
                </Link>
            </div>

            <!-- Right Controls: Cinema Location, Search, User Profile Dropdown, Hamburger -->
            <div class="flex items-center gap-2.5 sm:gap-4">
                <!-- Cinema Branch Selector / Location Display -->
                <div v-if="cinemas.length > 0" class="relative">
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium text-white/90 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e] sm:text-sm"
                        :aria-expanded="cinemaMenuOpen"
                        aria-haspopup="listbox"
                        @click="
                            cinemaMenuOpen = !cinemaMenuOpen;
                            userMenuOpen = false;
                        "
                    >
                        <MapPin class="size-4 text-[#f5b82e]" />
                        <span class="max-w-[130px] truncate sm:max-w-[200px]">
                            {{ activeCinemaLabel }}
                        </span>
                        <ChevronDown
                            class="size-3.5 text-white/60 transition-transform duration-200"
                            :class="{ 'rotate-180': cinemaMenuOpen }"
                        />
                    </button>

                    <div
                        v-if="cinemaMenuOpen"
                        class="fixed inset-0 z-40"
                        @click="cinemaMenuOpen = false"
                    />
                    <div
                        v-if="cinemaMenuOpen"
                        role="listbox"
                        class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-white/15 bg-[#18191d] py-1.5 shadow-2xl"
                    >
                        <button
                            type="button"
                            role="option"
                            :aria-selected="selectedCinema === 'all'"
                            :class="[
                                'flex w-full items-center justify-between px-4 py-2.5 text-left text-sm transition',
                                selectedCinema === 'all'
                                    ? 'bg-[#f5b82e]/15 font-semibold text-[#f5b82e]'
                                    : 'text-white/85 hover:bg-white/5 hover:text-white',
                            ]"
                            @click="selectCinema('all')"
                        >
                            <span>Semua Bioskop</span>
                        </button>
                        <button
                            v-for="cinema in cinemas"
                            :key="cinema.id"
                            type="button"
                            role="option"
                            :aria-selected="selectedCinema === cinema.id"
                            :class="[
                                'flex w-full flex-col px-4 py-2.5 text-left transition',
                                selectedCinema === cinema.id
                                    ? 'bg-[#f5b82e]/15 text-[#f5b82e]'
                                    : 'text-white/85 hover:bg-white/5 hover:text-white',
                            ]"
                            @click="selectCinema(cinema.id)"
                        >
                            <span class="text-sm font-medium">{{
                                cinema.label
                            }}</span>
                            <span
                                v-if="cinema.city"
                                class="text-xs text-[#9ca0aa]"
                                >{{ cinema.city }}</span
                            >
                        </button>
                    </div>
                </div>
                <div
                    v-else-if="activeCinemaDisplay"
                    class="inline-flex min-h-10 items-center gap-2 rounded-full px-3 py-1.5 text-xs font-medium text-white/90 sm:text-sm"
                >
                    <MapPin class="size-4 text-[#f5b82e]" />
                    <span class="max-w-[130px] truncate sm:max-w-[200px]">
                        {{ activeCinemaDisplay }}
                    </span>
                </div>

                <!-- Inline Search (Desktop) -->
                <div v-if="showSearch" class="hidden items-center sm:flex">
                    <div
                        v-if="searchOpen"
                        class="flex items-center gap-2 rounded-full border border-white/20 bg-[#18191d]/90 px-3.5 py-1.5"
                    >
                        <Search class="size-3.5 text-[#f5b82e]" />
                        <input
                            :value="searchQuery"
                            type="text"
                            placeholder="Cari film..."
                            aria-label="Cari judul film"
                            class="w-36 bg-transparent text-xs text-white placeholder-[#9ca0aa] focus:outline-none lg:w-44"
                            @input="
                                emit(
                                    'update:searchQuery',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                        <button
                            type="button"
                            aria-label="Tutup pencarian"
                            class="text-white/60 hover:text-white"
                            @click="toggleSearch"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                    <button
                        v-else
                        type="button"
                        aria-label="Cari film"
                        class="inline-flex size-10 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                        @click="toggleSearch"
                    >
                        <Search class="size-4" />
                    </button>
                </div>

                <!-- User Profile Dropdown (Includes Admin Panel when is_admin === true) -->
                <div v-if="user" class="relative hidden md:block">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2.5 rounded-full py-1 pr-2.5 pl-1 transition hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                        :aria-expanded="userMenuOpen"
                        aria-haspopup="menu"
                        @click="
                            userMenuOpen = !userMenuOpen;
                            cinemaMenuOpen = false;
                        "
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-full border border-[#f5b82e]/40 bg-[#202227] font-display text-xs font-bold text-[#f5b82e]"
                        >
                            {{ userInitials }}
                        </span>
                        <span
                            class="max-w-[120px] truncate text-xs font-medium text-white/90"
                        >
                            {{ user.name }}
                        </span>
                        <ChevronDown
                            class="size-3.5 text-white/60 transition-transform duration-200"
                            :class="{ 'rotate-180': userMenuOpen }"
                        />
                    </button>

                    <div
                        v-if="userMenuOpen"
                        class="fixed inset-0 z-40"
                        @click="userMenuOpen = false"
                    />
                    <div
                        v-if="userMenuOpen"
                        role="menu"
                        class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-xl border border-white/15 bg-[#18191d] py-1.5 shadow-2xl"
                    >
                        <div class="border-b border-white/10 px-4 py-2.5">
                            <p
                                class="truncate text-xs font-semibold text-white"
                            >
                                {{ user.name }}
                            </p>
                            <p class="truncate text-[11px] text-[#9ca0aa]">
                                {{ user.email }}
                            </p>
                        </div>

                        <Link
                            href="/my-bookings"
                            role="menuitem"
                            class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-white/85 transition hover:bg-white/5 hover:text-[#f5b82e]"
                            @click="userMenuOpen = false"
                        >
                            <Ticket class="size-3.5 text-[#f5b82e]" />
                            <span>Reservasi & Tiket Saya</span>
                        </Link>

                        <Link
                            v-if="user.is_admin"
                            href="/admin/schedules"
                            role="menuitem"
                            class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-[#f5b82e] transition hover:bg-[#f5b82e]/10"
                            @click="userMenuOpen = false"
                        >
                            <ShieldCheck class="size-3.5" />
                            <span>Admin Panel</span>
                        </Link>

                        <Link
                            href="/settings/profile"
                            role="menuitem"
                            class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-medium text-white/85 transition hover:bg-white/5 hover:text-white"
                            @click="userMenuOpen = false"
                        >
                            <Settings class="size-3.5 text-white/60" />
                            <span>Pengaturan Akun</span>
                        </Link>

                        <div class="mt-1 border-t border-white/10 pt-1">
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                role="menuitem"
                                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-xs font-medium text-red-300 transition hover:bg-red-500/10"
                                @click="userMenuOpen = false"
                            >
                                <LogOut class="size-3.5" />
                                <span>Keluar</span>
                            </Link>
                        </div>
                    </div>
                </div>
                <div v-else class="hidden items-center gap-2 md:flex">
                    <Link
                        href="/login"
                        class="inline-flex min-h-9 items-center rounded-full px-4 py-1.5 text-xs font-semibold text-white/90 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                    >
                        Masuk
                    </Link>
                    <Link
                        href="/register"
                        class="inline-flex min-h-9 items-center rounded-full bg-[#f5b82e] px-4 py-1.5 text-xs font-bold text-[#121212] transition hover:bg-[#ffc542] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                    >
                        Daftar
                    </Link>
                </div>

                <!-- Drawer / Hamburger Menu Button -->
                <button
                    type="button"
                    aria-label="Buka menu navigasi"
                    :aria-expanded="mobileMenuOpen"
                    class="inline-flex size-10 items-center justify-center rounded-full text-white/85 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X v-if="mobileMenuOpen" class="size-5" />
                    <Menu v-else class="size-5" />
                </button>
            </div>
        </div>

        <!-- Slide-down Menu Panel -->
        <div
            v-if="mobileMenuOpen"
            class="border-t border-white/10 bg-[#121212]/98 px-5 py-5 backdrop-blur-xl sm:px-8"
        >
            <div class="mx-auto flex max-w-[1400px] flex-col gap-3">
                <div v-if="showSearch" class="sm:hidden">
                    <div
                        class="flex items-center gap-2 rounded-lg border border-white/15 bg-[#18191d] px-3.5 py-2.5"
                    >
                        <Search class="size-4 text-[#f5b82e]" />
                        <input
                            :value="searchQuery"
                            type="text"
                            placeholder="Cari judul film..."
                            class="w-full bg-transparent text-sm text-white placeholder-[#9ca0aa] focus:outline-none"
                            @input="
                                emit(
                                    'update:searchQuery',
                                    ($event.target as HTMLInputElement).value,
                                )
                            "
                        />
                    </div>
                </div>
                <nav class="grid gap-1 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        href="/showtimes"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/90 hover:bg-white/5 hover:text-[#f5b82e]"
                        @click="mobileMenuOpen = false"
                    >
                        <Clapperboard class="size-4 text-[#f5b82e]" />
                        <span>Jadwal Tayang</span>
                    </Link>
                    <Link
                        href="/movies"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/90 hover:bg-white/5 hover:text-[#f5b82e]"
                        @click="mobileMenuOpen = false"
                    >
                        <Clapperboard class="size-4 text-white/60" />
                        <span>Semua Film</span>
                    </Link>
                    <Link
                        v-if="user"
                        href="/my-bookings"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/90 hover:bg-white/5 hover:text-[#f5b82e]"
                        @click="mobileMenuOpen = false"
                    >
                        <Ticket class="size-4 text-[#f5b82e]" />
                        <span>Reservasi & Tiket Saya</span>
                    </Link>
                    <Link
                        v-if="user?.is_admin"
                        href="/admin/schedules"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-[#f5b82e] hover:bg-[#f5b82e]/10"
                        @click="mobileMenuOpen = false"
                    >
                        <ShieldCheck class="size-4" />
                        <span>Admin Panel</span>
                    </Link>
                    <Link
                        v-if="!user"
                        href="/login"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-white/90 hover:bg-white/5 hover:text-[#f5b82e]"
                        @click="mobileMenuOpen = false"
                    >
                        <User class="size-4 text-[#f5b82e]" />
                        <span>Masuk / Daftar</span>
                    </Link>
                </nav>
            </div>
        </div>
    </header>
</template>
