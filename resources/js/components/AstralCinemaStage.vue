<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    Calendar,
    CalendarDays,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Clapperboard,
    Clock3,
    Info,
    MapPin,
    Play,
    Star,
    Ticket,
    User,
    X,
} from '@lucide/vue';
import gsap from 'gsap';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import CinemaNav, { type CinemaOption } from '@/components/CinemaNav.vue';

export type StageMode = 'schedule' | 'detail' | 'booking';

export type Showtime = {
    id_schedule: number;
    id_movie?: number;
    id_cinema?: number;
    show_date: string;
    show_time: string;
    price: string;
    seat_capacity: number;
    studio_name: string;
    booking_close_minutes?: number;
    movie: {
        id_movie?: number;
        tmdb_id?: number | null;
        title: string;
        genre?: string | null;
        duration_minute: number;
        rating_age?: string | null;
        release_date?: string | null;
        synopsis?: string | null;
        poster: string | null;
        backdrop?: string | null;
        trailer_url?: string | null;
    };
    cinema: {
        id_cinema?: number;
        name: string;
        city: string | null;
    };
};

export type CastMember = {
    name: string;
    character: string | null;
    photo: string | null;
};

export type MovieShowcase = {
    grade: number | null;
    country: string | null;
    language: string | null;
    director: string | null;
    synopsis: string | null;
    trailer_url: string | null;
    trailer_embed_url: string | null;
    cast: CastMember[];
};

type MovieGroup = {
    key: string;
    idMovie: number | null;
    title: string;
    genre: string | null;
    duration_minute: number;
    rating_age: string | null;
    synopsis: string | null;
    poster: string | null;
    backdrop: string | null;
    trailer_url: string | null;
    primaryDateIso: string;
    dayGroupLabel: string;
    schedules: Showtime[];
};

type SeatCell = {
    seatNumber: number;
    rowLetter: string;
    colNumber: number;
    seatCode: string;
};

type SeatRow = {
    rowLetter: string;
    seats: SeatCell[];
};

const props = withDefaults(
    defineProps<{
        schedules: Showtime[];
        movieShowcases?: Record<string, MovieShowcase>;
        occupiedSeatsBySchedule?: Record<string, number[]>;
        initialMode?: StageMode;
        initialScheduleId?: number | null;
        initialTimePicked?: boolean;
        maxSeats?: number;
        holdMinutes?: number;
        canReturnToSchedule?: boolean;
    }>(),
    {
        movieShowcases: () => ({}),
        occupiedSeatsBySchedule: () => ({}),
        initialMode: 'schedule',
        initialScheduleId: null,
        initialTimePicked: false,
        maxSeats: 6,
        holdMinutes: 10,
        canReturnToSchedule: true,
    },
);

// Core persistent canvas state
const mode = ref<StageMode>(props.initialMode);
const selectedCinema = ref<string>('all');
const selectedDateFilter = ref<string>('all');
const searchQuery = ref<string>('');
const activeMovieIndex = ref<number>(0);
const selectedScheduleId = ref<number | null>(
    props.initialScheduleId ?? props.schedules[0]?.id_schedule ?? null,
);
const scrollY = ref<number>(0);

// 2-Stage Seat Selection state
const hasPickedTime = ref<boolean>(props.initialTimePicked);
const dateStripCollapsed = ref<boolean>(props.initialTimePicked);
const ticketCardExpanded = ref<boolean>(props.initialTimePicked);
const hoveredSeatNumber = ref<number | null>(null);
const animatedPrice = ref<number>(0);
const loadingSeats = ref<boolean>(false);
const localOccupiedMap = ref<Record<string, number[]>>({
    ...props.occupiedSeatsBySchedule,
});

// Detail & Trailer UI state
const trailerModalOpen = ref<boolean>(false);
const castStartIndex = ref<number>(0);

// Booking form
const form = useForm<{ seats: number[] }>({ seats: [] });

// Schedule carousel outgoing snapshot for rotating vortex transition
const outgoingHeroImage = ref<string | null>(null);
const outgoingTitle = ref<string>('');
const outgoingGenre = ref<string>('');
const outgoingDigitTens = ref<string>('0');
const outgoingDigitOnes = ref<string>('1');
const outgoingDayLabel = ref<string>('');
const isCarouselTransitioning = ref<boolean>(false);
const isModeTransitioning = ref<boolean>(false);

// Refs: persistent backdrop & warp filter
const incomingBgRef = ref<HTMLElement | null>(null);
const outgoingBgRef = ref<HTMLElement | null>(null);
const displacementRef = ref<SVGFEDisplacementMapElement | null>(null);

// Refs: Schedule mode elements
const scheduleStageRef = ref<HTMLElement | null>(null);
const scheduleTitleRef = ref<HTMLElement | null>(null);
const scheduleMetaRef = ref<HTMLElement | null>(null);
const scheduleDayRef = ref<HTMLElement | null>(null);
const outgoingDayRef = ref<HTMLElement | null>(null);
const outgoingTitleRef = ref<HTMLElement | null>(null);
const outgoingMetaRef = ref<HTMLElement | null>(null);
const incomingOnesRef = ref<HTMLElement | null>(null);
const outgoingOnesRef = ref<HTMLElement | null>(null);
const incomingTensRef = ref<HTMLElement | null>(null);
const outgoingTensRef = ref<HTMLElement | null>(null);
const heroNumberWrapRef = ref<HTMLElement | null>(null);
const schedulePillsTrackRef = ref<HTMLElement | null>(null);
const heroCtaRowRef = ref<HTMLElement | null>(null);
const heroNavControlsRef = ref<HTMLElement | null>(null);
const carouselTrackRef = ref<HTMLElement | null>(null);
const nextRingCircleRef = ref<SVGCircleElement | null>(null);
const prevRingCircleRef = ref<SVGCircleElement | null>(null);

// Refs: Unified Film (Detail + Scrollable Booking) elements
const filmStageRef = ref<HTMLElement | null>(null);
const detailTitleRef = ref<HTMLElement | null>(null);
const detailSpecsRef = ref<HTMLElement | null>(null);
const detailPlayBtnRef = ref<HTMLElement | null>(null);
const detailCastRef = ref<HTMLElement | null>(null);
const detailStoryRef = ref<HTMLElement | null>(null);
const detailOrderBtnRef = ref<HTMLElement | null>(null);
const castTrackRef = ref<HTMLElement | null>(null);

const bookingSectionRef = ref<HTMLElement | null>(null);
const ticketCardRef = ref<HTMLElement | null>(null);
const screenArcRef = ref<HTMLElement | null>(null);
const seatGridRef = ref<HTMLElement | null>(null);

let activeTimeline: gsap.core.Timeline | null = null;

const prefersReducedMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const money = (value: string | number) =>
    `Rp ${Math.round(Number(value)).toLocaleString('id-ID')}`;

// Scroll-reactive backdrop opacity: fades smoothly into #121212 as user scrolls down to seat selection
const backdropImageOpacity = computed(() => {
    if (mode.value === 'schedule') return 0.6;
    const fadeFactor = Math.min(1, Math.max(0, scrollY.value / 540));
    return Number((0.48 - fadeFactor * 0.4).toFixed(3));
});

const todayIso = computed(() =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date()),
);

const tomorrowIso = computed(() =>
    new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(Date.now() + 86_400_000)),
);

function formatDateBadge(isoDate: string): {
    label: string;
    dayNum: string;
    weekday: string;
} {
    const clean = isoDate.slice(0, 10);
    const [y, m, d] = clean.split('-').map(Number);
    const dateObj = new Date(Date.UTC(y, m - 1, d, 12, 0, 0));
    const weekday = new Intl.DateTimeFormat('id-ID', {
        weekday: 'short',
        timeZone: 'UTC',
    }).format(dateObj);
    const shortMonth = new Intl.DateTimeFormat('id-ID', {
        month: 'short',
        timeZone: 'UTC',
    }).format(dateObj);

    let label = `${d} ${shortMonth}`;
    if (clean === todayIso.value) label = 'Today';
    else if (clean === tomorrowIso.value) label = 'Tomorrow';

    return {
        label,
        dayNum: String(d).padStart(2, '0'),
        weekday: weekday.toUpperCase(),
    };
}

const cinemaOptions = computed<CinemaOption[]>(() => {
    const map = new Map<string, CinemaOption>();
    for (const s of props.schedules) {
        const id = String(s.cinema.id_cinema ?? s.cinema.name);
        if (!map.has(id)) {
            map.set(id, {
                id,
                label: s.cinema.name,
                city: s.cinema.city,
            });
        }
    }
    return Array.from(map.values());
});

const availableDates = computed(() => {
    const set = new Set<string>();
    for (const s of props.schedules) {
        const cinemaId = String(s.cinema.id_cinema ?? s.cinema.name);
        if (
            selectedCinema.value === 'all' ||
            cinemaId === selectedCinema.value
        ) {
            set.add(s.show_date.slice(0, 10));
        }
    }
    return Array.from(set)
        .sort()
        .map((iso) => ({
            iso,
            ...formatDateBadge(iso),
        }));
});

const filteredSchedules = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.schedules.filter((s) => {
        const cinemaId = String(s.cinema.id_cinema ?? s.cinema.name);
        if (
            selectedCinema.value !== 'all' &&
            cinemaId !== selectedCinema.value
        ) {
            return false;
        }
        if (
            selectedDateFilter.value !== 'all' &&
            s.show_date.slice(0, 10) !== selectedDateFilter.value
        ) {
            return false;
        }
        if (
            q &&
            !s.movie.title.toLowerCase().includes(q) &&
            !s.cinema.name.toLowerCase().includes(q)
        ) {
            return false;
        }
        return true;
    });
});

const movieGroups = computed<MovieGroup[]>(() => {
    const map = new Map<string, MovieGroup>();
    for (const s of filteredSchedules.value) {
        const key = String(s.movie.id_movie ?? s.movie.title);
        const existing = map.get(key);
        if (existing) {
            existing.schedules.push(s);
        } else {
            const iso = s.show_date.slice(0, 10);
            map.set(key, {
                key,
                idMovie: s.movie.id_movie ?? null,
                title: s.movie.title,
                genre: s.movie.genre ?? null,
                duration_minute: s.movie.duration_minute,
                rating_age: s.movie.rating_age ?? null,
                synopsis: s.movie.synopsis ?? null,
                poster: s.movie.poster,
                backdrop: s.movie.backdrop ?? null,
                trailer_url: s.movie.trailer_url ?? null,
                primaryDateIso: iso,
                dayGroupLabel: formatDateBadge(iso).label,
                schedules: [s],
            });
        }
    }
    return Array.from(map.values());
});

const activeMovie = computed<MovieGroup | null>(
    () =>
        movieGroups.value[activeMovieIndex.value] ??
        movieGroups.value[0] ??
        null,
);

const activeSchedule = computed<Showtime | null>(() => {
    if (!activeMovie.value) return null;
    const found = activeMovie.value.schedules.find(
        (s) => s.id_schedule === selectedScheduleId.value,
    );
    return found ?? activeMovie.value.schedules[0] ?? null;
});

const activeCinemaNavbarLabel = computed(() => {
    if (!activeSchedule.value) return null;
    return activeSchedule.value.cinema.city
        ? `${activeSchedule.value.cinema.name} (${activeSchedule.value.cinema.city})`
        : activeSchedule.value.cinema.name;
});

const activeHeroImage = computed(
    () => activeMovie.value?.backdrop || activeMovie.value?.poster || null,
);

const activeShowcase = computed<MovieShowcase>(() => {
    const empty: MovieShowcase = {
        grade: null,
        country: null,
        language: null,
        director: null,
        synopsis: null,
        trailer_url: null,
        trailer_embed_url: null,
        cast: [],
    };
    const movieKey = activeMovie.value?.idMovie
        ? String(activeMovie.value.idMovie)
        : null;
    if (!movieKey) return empty;
    return props.movieShowcases[movieKey] ?? empty;
});

const resolvedSynopsis = computed(() => {
    const raw =
        activeMovie.value?.synopsis?.trim() ||
        activeShowcase.value.synopsis?.trim() ||
        '';
    return raw !== '' ? raw : null;
});

const resolvedTrailerUrl = computed(
    () =>
        activeMovie.value?.trailer_url ||
        activeShowcase.value.trailer_url ||
        null,
);

const resolvedTrailerEmbedUrl = computed(() => {
    if (activeShowcase.value.trailer_embed_url) {
        return activeShowcase.value.trailer_embed_url;
    }
    const url = resolvedTrailerUrl.value;
    if (!url) return null;
    const match = url.match(
        /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{11})/,
    );
    return match?.[1]
        ? `https://www.youtube.com/embed/${match[1]}?autoplay=1&rel=0`
        : null;
});

const castList = computed<CastMember[]>(() => activeShowcase.value.cast ?? []);

const castProgressPercent = computed(() => {
    const total = castList.value.length;
    if (total <= 3) return 100;
    const maxStart = Math.max(1, total - 3);
    return Math.min(
        100,
        Math.round(((castStartIndex.value + 1) / (maxStart + 1)) * 100),
    );
});

const filledStars = computed(() => {
    const grade = activeShowcase.value.grade;
    if (!grade || grade <= 0) return 0;
    return Math.max(1, Math.min(5, Math.round(grade / 2)));
});

const activeDateLabel = computed(() => {
    if (selectedDateFilter.value !== 'all') {
        return formatDateBadge(selectedDateFilter.value).label;
    }
    if (activeSchedule.value) {
        return formatDateBadge(activeSchedule.value.show_date).label;
    }
    return 'Today';
});

const formattedIndex = computed(() =>
    String(activeMovieIndex.value + 1).padStart(2, '0'),
);
const currentDigitTens = computed(() => formattedIndex.value[0] ?? '0');
const currentDigitOnes = computed(() => formattedIndex.value[1] ?? '1');

const queuedMovies = computed(() => {
    const total = movieGroups.value.length;
    if (total === 0) return [];
    const items: Array<{
        movie: MovieGroup;
        originalIndex: number;
        displayNumber: string;
        showDayHeader: boolean;
    }> = [];

    const count = total > 1 ? total - 1 : 1;
    let lastDay = activeDateLabel.value;
    for (let offset = 1; offset <= count; offset++) {
        const idx = total > 1 ? (activeMovieIndex.value + offset) % total : 0;
        const m = movieGroups.value[idx];
        if (!m) continue;
        const showDayHeader = offset === 1 || m.dayGroupLabel !== lastDay;
        lastDay = m.dayGroupLabel;
        items.push({
            movie: m,
            originalIndex: idx,
            displayNumber: String(idx + 1).padStart(2, '0'),
            showDayHeader,
        });
    }
    return items;
});

// Booking Mode: Date & Time Helpers
const currentShowDateIso = computed(() =>
    (activeSchedule.value?.show_date ?? todayIso.value).slice(0, 10),
);

const currentMonthName = computed(() => {
    const [y, m, d] = currentShowDateIso.value.split('-').map(Number);
    const dateObj = new Date(Date.UTC(y, m - 1, d, 12, 0, 0));
    return new Intl.DateTimeFormat('id-ID', {
        month: 'long',
        timeZone: 'UTC',
    }).format(dateObj);
});

const formattedDotDate = computed(() => {
    const [y, m, d] = currentShowDateIso.value.split('-');
    return `${d}.${m}.${y}`;
});

const bookingDateStrip = computed(() => {
    const movieSchedules = activeMovie.value?.schedules ?? [];
    const schedulesByDate = new Map<string, Showtime[]>();
    for (const s of movieSchedules) {
        const iso = s.show_date.slice(0, 10);
        const list = schedulesByDate.get(iso) ?? [];
        list.push(s);
        schedulesByDate.set(iso, list);
    }

    const [y, m, d] = currentShowDateIso.value.split('-').map(Number);
    const baseUtc = Date.UTC(y, m - 1, d, 12, 0, 0);

    const dateSet = new Set<string>(schedulesByDate.keys());
    for (const offset of [-1, 0, 1, 2, 3, 4]) {
        if (dateSet.size >= 5) break;
        const dt = new Date(baseUtc + offset * 86_400_000);
        dateSet.add(dt.toISOString().slice(0, 10));
    }

    return Array.from(dateSet)
        .sort()
        .slice(0, 5)
        .map((iso) => {
            const [yy, mm, dd] = iso.split('-').map(Number);
            const dt = new Date(Date.UTC(yy, mm - 1, dd, 12, 0, 0));
            const weekday = new Intl.DateTimeFormat('en-US', {
                weekday: 'short',
                timeZone: 'UTC',
            })
                .format(dt)
                .toUpperCase();
            const matching = schedulesByDate.get(iso) ?? [];
            return {
                iso,
                dayNum: String(dd).padStart(2, '0'),
                weekday,
                hasSchedule: matching.length > 0,
                firstScheduleId: matching[0]?.id_schedule ?? null,
            };
        });
});

const timeSlotsForBookingDate = computed(() => {
    const movieSchedules = activeMovie.value?.schedules ?? [];
    return movieSchedules.filter(
        (s) => s.show_date.slice(0, 10) === currentShowDateIso.value,
    );
});

const currentOccupiedSet = computed(() => {
    const id = activeSchedule.value?.id_schedule;
    if (!id) return new Set<number>();
    const list = localOccupiedMap.value[String(id)] ?? [];
    return new Set<number>(list);
});

const seatRows = computed<SeatRow[]>(() => {
    const total = activeSchedule.value?.seat_capacity ?? 0;
    if (total <= 0) return [];

    const rowCounts: number[] = [];
    if (total === 40) {
        rowCounts.push(7, 7, 8, 8, 10);
    } else if (total === 50) {
        rowCounts.push(8, 10, 10, 10, 12);
    } else if (total === 60) {
        rowCounts.push(10, 10, 12, 14, 14);
    } else {
        const backWidth = total <= 36 ? 8 : total <= 64 ? 10 : 12;
        const frontWidth = Math.max(4, backWidth - 2);
        let remaining = total;
        let r = 0;
        while (remaining > 0) {
            const w = r < 2 ? frontWidth : backWidth;
            if (remaining < w && rowCounts.length > 0) {
                rowCounts.unshift(remaining);
                remaining = 0;
            } else {
                const take = Math.min(w, remaining);
                rowCounts.push(take);
                remaining -= take;
            }
            r++;
        }
        rowCounts.sort((a, b) => a - b);
    }

    const rows: SeatRow[] = [];
    let currentSeat = 1;
    for (let rIdx = 0; rIdx < rowCounts.length; rIdx++) {
        const count = rowCounts[rIdx] ?? 0;
        const rowLetter = String.fromCharCode(65 + rIdx);
        const rowSeats: SeatCell[] = [];
        for (let c = 1; c <= count; c++) {
            rowSeats.push({
                seatNumber: currentSeat,
                rowLetter,
                colNumber: c,
                seatCode: `${rowLetter}${c}`,
            });
            currentSeat++;
        }
        rows.push({ rowLetter, seats: rowSeats });
    }
    return rows;
});

const seatMetaMap = computed(() => {
    const map = new Map<number, SeatCell>();
    for (const r of seatRows.value) {
        for (const s of r.seats) {
            map.set(s.seatNumber, s);
        }
    }
    return map;
});

const selectedSeatCells = computed<SeatCell[]>(() =>
    form.seats.map(
        (num) =>
            seatMetaMap.value.get(num) ?? {
                seatNumber: num,
                rowLetter: 'A',
                colNumber: num,
                seatCode: `A${num}`,
            },
    ),
);

const totalBookingPrice = computed(
    () => Number(activeSchedule.value?.price ?? 0) * form.seats.length,
);

watch(
    totalBookingPrice,
    (nextVal) => {
        if (prefersReducedMotion()) {
            animatedPrice.value = nextVal;
            return;
        }
        const proxy = { val: animatedPrice.value };
        gsap.to(proxy, {
            val: nextVal,
            duration: 0.35,
            ease: 'power2.out',
            onUpdate: () => {
                animatedPrice.value = Math.round(proxy.val);
            },
        });
    },
    { immediate: true },
);

watch(
    () => props.initialScheduleId,
    (id) => {
        if (!id) return;
        const idx = movieGroups.value.findIndex((g) =>
            g.schedules.some((s) => s.id_schedule === id),
        );
        if (idx >= 0) {
            activeMovieIndex.value = idx;
            selectedScheduleId.value = id;
        }
    },
    { immediate: true },
);

watch(movieGroups, (groups) => {
    if (activeMovieIndex.value >= groups.length) {
        activeMovieIndex.value = 0;
    }
    const activeGroup = groups[activeMovieIndex.value];
    if (
        activeGroup &&
        !activeGroup.schedules.some(
            (s) => s.id_schedule === selectedScheduleId.value,
        )
    ) {
        selectedScheduleId.value =
            activeGroup.schedules[0]?.id_schedule ?? null;
    }
});

async function refreshOccupiedSeats(scheduleId: number): Promise<void> {
    loadingSeats.value = true;
    try {
        const res = await fetch(`/showtimes/${scheduleId}/seats`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const payload = (await res.json()) as {
                id_schedule: number;
                occupiedSeats: number[];
            };
            localOccupiedMap.value = {
                ...localOccupiedMap.value,
                [String(scheduleId)]: payload.occupiedSeats ?? [],
            };
        }
    } catch {
        // Retain cached seats if network hiccups
    } finally {
        loadingSeats.value = false;
    }
}

function syncHistoryUrl(
    targetMode: StageMode,
    scheduleId: number | null,
    replace = false,
): void {
    if (typeof window === 'undefined') return;
    let url = '/showtimes';
    if (targetMode === 'detail' && scheduleId) {
        url = `/showtimes/${scheduleId}?view=detail`;
    } else if (targetMode === 'booking' && scheduleId) {
        url = `/showtimes/${scheduleId}`;
    }
    const state = {
        astralMode: targetMode,
        scheduleId,
        movieIndex: activeMovieIndex.value,
    };
    if (replace) {
        window.history.replaceState(state, '', url);
    } else {
        window.history.pushState(state, '', url);
    }
}

function runAstralCarouselTransition(
    targetIndex: number,
    direction: 1 | -1,
    buttonKind: 'next' | 'prev' | 'card' = 'next',
): void {
    const groups = movieGroups.value;
    if (
        mode.value !== 'schedule' ||
        groups.length <= 1 ||
        targetIndex === activeMovieIndex.value ||
        targetIndex < 0 ||
        targetIndex >= groups.length
    ) {
        return;
    }

    const prevMovieObj = activeMovie.value;
    const prevIndexStr = formattedIndex.value;
    const prevImage = activeHeroImage.value;
    const prevDay = activeDateLabel.value;

    outgoingHeroImage.value = prevImage;
    outgoingTitle.value = prevMovieObj?.title ?? '';
    outgoingGenre.value =
        prevMovieObj?.genre || `${prevMovieObj?.duration_minute ?? 0} mnt`;
    outgoingDigitTens.value = prevIndexStr[0] ?? '0';
    outgoingDigitOnes.value = prevIndexStr[1] ?? '1';
    outgoingDayLabel.value = prevDay;

    const tensChanged =
        (String(targetIndex + 1).padStart(2, '0')[0] ?? '0') !==
        outgoingDigitTens.value;
    const dayChanged =
        (groups[targetIndex]?.dayGroupLabel ?? prevDay) !== prevDay;

    isCarouselTransitioning.value = true;
    activeMovieIndex.value = targetIndex;
    selectedScheduleId.value =
        groups[targetIndex]?.schedules[0]?.id_schedule ?? null;

    if (prefersReducedMotion()) {
        isCarouselTransitioning.value = false;
        return;
    }

    if (activeTimeline) {
        activeTimeline.kill();
    }

    nextTick(() => {
        const tl = gsap.timeline({
            onComplete: () => {
                isCarouselTransitioning.value = false;
                if (displacementRef.value) {
                    gsap.set(displacementRef.value, { attr: { scale: 0 } });
                }
            },
        });
        activeTimeline = tl;

        const ringEl =
            buttonKind === 'prev'
                ? prevRingCircleRef.value
                : nextRingCircleRef.value;
        if (ringEl) {
            tl.fromTo(
                ringEl,
                { strokeDashoffset: 138, opacity: 1 },
                { strokeDashoffset: 0, duration: 0.72, ease: 'power2.inOut' },
                0,
            );
        }

        if (outgoingBgRef.value) {
            tl.fromTo(
                outgoingBgRef.value,
                { opacity: 1, scale: 1, rotation: 0, filter: 'blur(0px)' },
                {
                    opacity: 0,
                    scale: 1.36,
                    rotation: direction * 15,
                    filter: 'blur(10px)',
                    duration: 0.88,
                    ease: 'power3.inOut',
                },
                0,
            );
        }

        if (incomingBgRef.value) {
            tl.fromTo(
                incomingBgRef.value,
                {
                    opacity: 0,
                    scale: 1.42,
                    rotation: -direction * 18,
                    filter: 'blur(12px)',
                },
                {
                    opacity: 1,
                    scale: 1,
                    rotation: 0,
                    filter: 'blur(0px)',
                    duration: 0.92,
                    ease: 'expo.out',
                },
                0.04,
            );
        }

        if (displacementRef.value) {
            tl.fromTo(
                displacementRef.value,
                { attr: { scale: 0 } },
                {
                    attr: { scale: 42 },
                    duration: 0.32,
                    ease: 'power2.in',
                    yoyo: true,
                    repeat: 1,
                },
                0,
            );
        }

        if (outgoingOnesRef.value && incomingOnesRef.value) {
            tl.fromTo(
                outgoingOnesRef.value,
                { yPercent: 0, opacity: 1 },
                {
                    yPercent: direction > 0 ? 105 : -105,
                    opacity: 0,
                    duration: 0.44,
                    ease: 'power3.in',
                },
                0,
            );
            tl.fromTo(
                incomingOnesRef.value,
                { yPercent: direction > 0 ? -105 : 105, opacity: 0 },
                { yPercent: 0, opacity: 1, duration: 0.56, ease: 'expo.out' },
                0.14,
            );
        }

        if (tensChanged && outgoingTensRef.value && incomingTensRef.value) {
            tl.fromTo(
                outgoingTensRef.value,
                { yPercent: 0, opacity: 1 },
                {
                    yPercent: direction > 0 ? 105 : -105,
                    opacity: 0,
                    duration: 0.44,
                    ease: 'power3.in',
                },
                0,
            );
            tl.fromTo(
                incomingTensRef.value,
                { yPercent: direction > 0 ? -105 : 105, opacity: 0 },
                { yPercent: 0, opacity: 1, duration: 0.56, ease: 'expo.out' },
                0.14,
            );
        }

        if (outgoingTitleRef.value) {
            tl.fromTo(
                outgoingTitleRef.value,
                { xPercent: 0, opacity: 1 },
                {
                    xPercent: -102,
                    opacity: 0.15,
                    duration: 0.38,
                    ease: 'power2.in',
                },
                0,
            );
        }
        if (scheduleTitleRef.value) {
            tl.fromTo(
                scheduleTitleRef.value,
                { xPercent: -104, opacity: 0.25 },
                { xPercent: 0, opacity: 1, duration: 0.64, ease: 'expo.out' },
                0.16,
            );
        }

        if (outgoingMetaRef.value) {
            tl.fromTo(
                outgoingMetaRef.value,
                { xPercent: 0, opacity: 1 },
                { xPercent: -100, opacity: 0, duration: 0.34, ease: 'power2.in' },
                0.03,
            );
        }
        if (scheduleMetaRef.value) {
            tl.fromTo(
                scheduleMetaRef.value,
                { xPercent: -100, opacity: 0 },
                { xPercent: 0, opacity: 1, duration: 0.62, ease: 'expo.out' },
                0.22,
            );
        }

        if (dayChanged && outgoingDayRef.value && scheduleDayRef.value) {
            tl.fromTo(
                outgoingDayRef.value,
                { x: 0, opacity: 1 },
                { x: -48, opacity: 0, duration: 0.32, ease: 'power2.in' },
                0,
            );
            tl.fromTo(
                scheduleDayRef.value,
                { x: 48, opacity: 0 },
                { x: 0, opacity: 1, duration: 0.54, ease: 'expo.out' },
                0.18,
            );
        }

        if (schedulePillsTrackRef.value) {
            const pills =
                schedulePillsTrackRef.value.querySelectorAll('[data-time-pill]');
            tl.fromTo(
                pills,
                { x: -72, opacity: 0 },
                {
                    x: 0,
                    opacity: 1,
                    duration: 0.52,
                    stagger: 0.035,
                    ease: 'expo.out',
                },
                0.18,
            );
        }

        if (carouselTrackRef.value) {
            const cards =
                carouselTrackRef.value.querySelectorAll('[data-poster-card]');
            const slideOffset = direction > 0 ? 188 : -188;
            tl.fromTo(
                cards,
                { x: slideOffset },
                { x: 0, duration: 0.78, stagger: 0.03, ease: 'expo.out' },
                0.02,
            );
        }
    });
}

function nextMovie(): void {
    if (movieGroups.value.length <= 1) return;
    const next = (activeMovieIndex.value + 1) % movieGroups.value.length;
    runAstralCarouselTransition(next, 1, 'next');
}

function prevMovie(): void {
    if (movieGroups.value.length <= 1) return;
    const prev =
        (activeMovieIndex.value - 1 + movieGroups.value.length) %
        movieGroups.value.length;
    runAstralCarouselTransition(prev, -1, 'prev');
}

function selectMovieFromCard(targetIdx: number): void {
    if (targetIdx === activeMovieIndex.value) return;
    const dir: 1 | -1 = targetIdx > activeMovieIndex.value ? 1 : -1;
    runAstralCarouselTransition(targetIdx, dir, 'card');
}

/**
 * Scroll smoothly down to the ticket & seat selection section within the unified film page
 */
function scrollToBookingSection(instant = false): void {
    nextTick(() => {
        bookingSectionRef.value?.scrollIntoView({
            behavior: instant || prefersReducedMotion() ? 'auto' : 'smooth',
            block: 'start',
        });
    });
}

/**
 * Opens unified scrollable Film Page (Detail top + Seat Selection bottom).
 * - When `scrollToSeats === false` ('More'): Title FLIPs smoothly from bottom-left to top-left at scrollY=0.
 * - When `scrollToSeats === true` ('Book a session'): Opens unified page and immediately smooth-scrolls to #booking-section.
 */
async function enterFilmPage(
    scrollToSeats = false,
    prePickedTime = false,
    pushHistory = true,
): Promise<void> {
    if (isModeTransitioning.value) return;
    const schedId = activeSchedule.value?.id_schedule ?? null;
    const targetMode: StageMode = scrollToSeats ? 'booking' : 'detail';

    if (schedId) {
        void refreshOccupiedSeats(schedId);
    }

    if (prefersReducedMotion()) {
        form.seats = [];
        hasPickedTime.value = prePickedTime;
        dateStripCollapsed.value = prePickedTime;
        ticketCardExpanded.value = prePickedTime;
        mode.value = targetMode;
        castStartIndex.value = 0;
        if (pushHistory) syncHistoryUrl(targetMode, schedId);
        if (scrollToSeats) scrollToBookingSection(true);
        else window.scrollTo({ top: 0, behavior: 'auto' });
        return;
    }

    isModeTransitioning.value = true;
    const oldRect =
        !scrollToSeats && mode.value === 'schedule'
            ? (scheduleTitleRef.value?.getBoundingClientRect() ?? null)
            : null;

    if (mode.value === 'schedule') {
        const fadeTargets = [
            heroNumberWrapRef.value,
            scheduleDayRef.value,
            scheduleMetaRef.value,
            schedulePillsTrackRef.value,
            heroCtaRowRef.value,
            heroNavControlsRef.value,
            carouselTrackRef.value,
        ].filter(Boolean);

        if (fadeTargets.length > 0) {
            await new Promise<void>((resolve) => {
                gsap.to(fadeTargets, {
                    opacity: 0,
                    duration: 0.22,
                    ease: 'power2.out',
                    onComplete: resolve,
                });
            });
        }
    }

    form.seats = [];
    hasPickedTime.value = prePickedTime;
    dateStripCollapsed.value = prePickedTime;
    ticketCardExpanded.value = prePickedTime;
    mode.value = targetMode;
    castStartIndex.value = 0;
    if (pushHistory) syncHistoryUrl(targetMode, schedId);

    await nextTick();
    window.scrollTo({ top: 0, behavior: 'auto' });

    const newTitleEl = detailTitleRef.value;
    if (newTitleEl && oldRect && !scrollToSeats) {
        const newRect = newTitleEl.getBoundingClientRect();
        const dx = oldRect.left - newRect.left;
        const dy = oldRect.top - newRect.top;
        const scale =
            newRect.height > 0
                ? Math.max(0.7, Math.min(1.3, oldRect.height / newRect.height))
                : 1;

        newTitleEl.style.transition = 'none';
        newTitleEl.style.transformOrigin = 'top left';
        newTitleEl.style.transform = `translate3d(${dx}px, ${dy}px, 0) scale(${scale})`;
        void newTitleEl.offsetHeight;
        newTitleEl.style.transition =
            'transform 500ms cubic-bezier(0.22, 1, 0.36, 1)';
        newTitleEl.style.transform = 'translate3d(0, 0, 0) scale(1)';
    }

    const tl = gsap.timeline({
        onComplete: () => {
            isModeTransitioning.value = false;
            if (newTitleEl) {
                newTitleEl.style.transition = '';
                newTitleEl.style.transform = '';
                newTitleEl.style.transformOrigin = '';
            }
        },
    });

    if (detailSpecsRef.value) {
        const rows = detailSpecsRef.value.querySelectorAll('[data-spec-row]');
        tl.fromTo(
            rows,
            { y: 14, opacity: 0 },
            {
                y: 0,
                opacity: 1,
                duration: 0.42,
                stagger: 0.035,
                ease: 'power3.out',
            },
            0.18,
        );
    }

    if (detailPlayBtnRef.value) {
        tl.fromTo(
            detailPlayBtnRef.value,
            { scale: 0.65, opacity: 0 },
            { scale: 1, opacity: 1, duration: 0.45, ease: 'back.out(1.7)' },
            0.2,
        );
    }

    if (detailCastRef.value) {
        const cards = detailCastRef.value.querySelectorAll('[data-cast-card]');
        tl.fromTo(
            cards,
            { y: 20, opacity: 0 },
            {
                y: 0,
                opacity: 1,
                duration: 0.45,
                stagger: 0.045,
                ease: 'power3.out',
            },
            0.2,
        );
    }

    if (detailStoryRef.value) {
        tl.fromTo(
            detailStoryRef.value,
            { y: 18, opacity: 0 },
            { y: 0, opacity: 1, duration: 0.44, ease: 'power3.out' },
            0.25,
        );
    }

    if (detailOrderBtnRef.value) {
        tl.fromTo(
            detailOrderBtnRef.value,
            { y: 16, opacity: 0 },
            { y: 0, opacity: 1, duration: 0.4, ease: 'power3.out' },
            0.28,
        );
    }

    if (scrollToSeats) {
        setTimeout(() => {
            scrollToBookingSection(false);
        }, 90);
    }
}

async function enterScheduleMode(pushHistory = true): Promise<void> {
    if (!props.canReturnToSchedule) {
        router.visit('/showtimes');
        return;
    }
    if (isModeTransitioning.value || mode.value === 'schedule') return;

    if (prefersReducedMotion()) {
        mode.value = 'schedule';
        if (pushHistory) syncHistoryUrl('schedule', null);
        window.scrollTo({ top: 0, behavior: 'auto' });
        return;
    }

    isModeTransitioning.value = true;
    if (filmStageRef.value) {
        await new Promise<void>((resolve) => {
            gsap.to(filmStageRef.value, {
                y: 20,
                opacity: 0,
                duration: 0.24,
                ease: 'power2.in',
                onComplete: resolve,
            });
        });
    }

    mode.value = 'schedule';
    if (pushHistory) syncHistoryUrl('schedule', null);

    await nextTick();
    window.scrollTo({ top: 0, behavior: 'auto' });

    if (scheduleStageRef.value) {
        gsap.fromTo(
            scheduleStageRef.value,
            { opacity: 0, y: 16 },
            {
                opacity: 1,
                y: 0,
                duration: 0.42,
                ease: 'power3.out',
                onComplete: () => {
                    isModeTransitioning.value = false;
                },
            },
        );
    } else {
        isModeTransitioning.value = false;
    }
}

function handleLogoClick(event: MouseEvent): void {
    if (mode.value !== 'schedule' && props.canReturnToSchedule) {
        event.preventDefault();
        void enterScheduleMode(true);
    }
}

function pickBookingDate(firstScheduleId: number | null): void {
    if (!firstScheduleId) return;
    selectedScheduleId.value = firstScheduleId;
    form.seats = [];
    void refreshOccupiedSeats(firstScheduleId);
}

function pickTimeSlot(slot: Showtime): void {
    const isSameSlot =
        hasPickedTime.value && selectedScheduleId.value === slot.id_schedule;
    if (isSameSlot) return;

    selectedScheduleId.value = slot.id_schedule;
    hasPickedTime.value = true;
    dateStripCollapsed.value = true;
    ticketCardExpanded.value = true;
    form.seats = [];
    syncHistoryUrl('booking', slot.id_schedule, true);
    void refreshOccupiedSeats(slot.id_schedule);
}

function reopenDateStrip(): void {
    dateStripCollapsed.value = !dateStripCollapsed.value;
}

function toggleSeat(seatNumber: number, event?: MouseEvent): void {
    if (
        !hasPickedTime.value ||
        currentOccupiedSet.value.has(seatNumber) ||
        form.processing
    ) {
        return;
    }

    const isRemoving = form.seats.includes(seatNumber);
    if (isRemoving) {
        form.seats = form.seats.filter((n) => n !== seatNumber);
    } else if (form.seats.length < props.maxSeats) {
        form.seats = [...form.seats, seatNumber].sort((a, b) => a - b);
    } else {
        return;
    }

    if (!prefersReducedMotion() && event?.currentTarget) {
        const btn = event.currentTarget as HTMLElement;
        gsap.fromTo(
            btn,
            { scale: isRemoving ? 0.84 : 1.26 },
            {
                scale: 1,
                duration: 0.34,
                ease: 'back.out(2.4)',
                overwrite: true,
            },
        );
    }

    if (!isRemoving && !prefersReducedMotion()) {
        nextTick(() => {
            if (!ticketCardRef.value) return;
            const rowEl = ticketCardRef.value.querySelector(
                `[data-seat-row="${seatNumber}"]`,
            );
            if (rowEl) {
                gsap.fromTo(
                    rowEl,
                    { x: -18, opacity: 0 },
                    { x: 0, opacity: 1, duration: 0.32, ease: 'power3.out' },
                );
            }
        });
    }
}

function holdSeats(): void {
    if (!activeSchedule.value || form.seats.length === 0) return;
    form.post(`/showtimes/${activeSchedule.value.id_schedule}/book`, {
        preserveScroll: true,
        onError: () => {
            if (activeSchedule.value) {
                void refreshOccupiedSeats(activeSchedule.value.id_schedule);
            }
        },
    });
}

function slideCast(dir: 1 | -1): void {
    const total = castList.value.length;
    if (total <= 3) return;
    const maxStart = Math.max(0, total - 3);
    castStartIndex.value = Math.max(
        0,
        Math.min(maxStart, castStartIndex.value + dir),
    );
    nextTick(() => {
        const track = castTrackRef.value;
        if (!track) return;
        const card = track.querySelector<HTMLElement>(
            `[data-cast-idx="${castStartIndex.value}"]`,
        );
        if (card) {
            card.scrollIntoView({
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                inline: 'start',
                block: 'nearest',
            });
        }
    });
}

function openTrailerModal(): void {
    if (resolvedTrailerEmbedUrl.value) {
        trailerModalOpen.value = true;
    } else if (resolvedTrailerUrl.value) {
        window.open(resolvedTrailerUrl.value, '_blank', 'noopener,noreferrer');
    }
}

function resetFilters(): void {
    selectedCinema.value = 'all';
    selectedDateFilter.value = 'all';
    searchQuery.value = '';
}

function handleWindowScroll(): void {
    if (typeof window === 'undefined') return;
    scrollY.value = window.scrollY;
}

function handleKeydown(e: KeyboardEvent): void {
    const tag = (e.target as HTMLElement | null)?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
    if (e.key === 'Escape' && trailerModalOpen.value) {
        trailerModalOpen.value = false;
        return;
    }
    if (mode.value === 'schedule') {
        if (e.key === 'ArrowRight') nextMovie();
        else if (e.key === 'ArrowLeft') prevMovie();
    }
}

function handlePopstate(e: PopStateEvent): void {
    const st = e.state as {
        astralMode?: StageMode;
        scheduleId?: number | null;
        movieIndex?: number;
    } | null;
    if (!st || !st.astralMode) {
        if (props.canReturnToSchedule) {
            void enterScheduleMode(false);
        }
        return;
    }
    if (typeof st.movieIndex === 'number') {
        activeMovieIndex.value = st.movieIndex;
    }
    if (st.scheduleId) {
        selectedScheduleId.value = st.scheduleId;
    }
    if (st.astralMode === 'schedule') {
        void enterScheduleMode(false);
    } else if (st.astralMode === 'detail') {
        void enterFilmPage(false, false, false);
    } else if (st.astralMode === 'booking') {
        void enterFilmPage(true, false, false);
    }
}

onMounted(() => {
    if (activeMovie.value && !selectedScheduleId.value) {
        selectedScheduleId.value =
            activeMovie.value.schedules[0]?.id_schedule ?? null;
    }
    syncHistoryUrl(mode.value, selectedScheduleId.value, true);
    handleWindowScroll();
    window.addEventListener('scroll', handleWindowScroll, { passive: true });
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('popstate', handlePopstate);

    if (props.initialMode === 'booking') {
        setTimeout(() => {
            scrollToBookingSection(true);
        }, 80);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleWindowScroll);
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('popstate', handlePopstate);
    if (activeTimeline) {
        activeTimeline.kill();
    }
});
</script>

<template>
    <Head
        :title="
            mode === 'booking' && activeMovie
                ? `Pilih Kursi · ${activeMovie.title} | CINEM4`
                : mode === 'detail' && activeMovie
                  ? `${activeMovie.title} | CINEM4`
                  : 'Jadwal Tayang | CINEM4'
        "
    />

    <div
        class="astral-theme relative min-h-screen overflow-x-hidden bg-[#121212] text-white"
    >
        <!-- SVG Radial Swirl Displacement Filter for Schedule Carousel Switching -->
        <svg
            class="pointer-events-none fixed h-0 w-0 opacity-0"
            aria-hidden="true"
        >
            <defs>
                <filter
                    id="astral-backdrop-warp"
                    x="-20%"
                    y="-20%"
                    width="140%"
                    height="140%"
                >
                    <feTurbulence
                        type="fractalNoise"
                        baseFrequency="0.012"
                        numOctaves="2"
                        result="warpNoise"
                    />
                    <feDisplacementMap
                        ref="displacementRef"
                        in="SourceGraphic"
                        in2="warpNoise"
                        scale="0"
                        xChannelSelector="R"
                        yChannelSelector="G"
                    />
                </filter>
            </defs>
        </svg>

        <!-- PERSISTENT ZERO-FLICKER BACKDROP STAGE (Smoothly fades into #121212 on scroll) -->
        <div
            class="pointer-events-none fixed inset-0 z-0 overflow-hidden bg-[#121212]"
        >
            <div
                class="absolute inset-0"
                style="filter: url(#astral-backdrop-warp)"
            >
                <!-- Outgoing Backdrop Layer (only visible during movie carousel switch) -->
                <div
                    v-show="isCarouselTransitioning && outgoingHeroImage"
                    ref="outgoingBgRef"
                    class="absolute -inset-12 origin-[54%_44%] will-change-transform"
                >
                    <img
                        v-if="outgoingHeroImage"
                        :src="outgoingHeroImage"
                        alt=""
                        class="h-full w-full object-cover object-center opacity-60"
                    />
                </div>

                <!-- Stationary Active Movie Backdrop -->
                <div
                    ref="incomingBgRef"
                    class="absolute -inset-12 origin-[54%_44%] will-change-transform"
                >
                    <img
                        v-if="activeHeroImage"
                        :src="activeHeroImage"
                        :alt="activeMovie?.title ?? ''"
                        :style="{ opacity: backdropImageOpacity }"
                        class="h-full w-full object-cover object-center transition-opacity duration-300 ease-out"
                    />
                    <div v-else class="h-full w-full bg-[#121212]" />
                </div>
            </div>

            <!-- Adaptive Vignettes blending cleanly into #121212 -->
            <div
                class="absolute inset-0"
                style="
                    background: linear-gradient(
                        90deg,
                        #121212 0%,
                        rgba(18, 18, 18, 0.82) 48%,
                        rgba(18, 18, 18, 0.25) 100%
                    );
                "
            />
            <div
                class="absolute inset-0"
                style="
                    background: linear-gradient(
                        180deg,
                        rgba(18, 18, 18, 0.55) 0%,
                        rgba(18, 18, 18, 0.2) 45%,
                        #121212 100%
                    );
                "
            />
        </div>

        <!-- FIXED TOP NAVBAR -->
        <CinemaNav
            v-model:selected-cinema="selectedCinema"
            v-model:search-query="searchQuery"
            :cinemas="cinemaOptions"
            :active-cinema-display="activeCinemaNavbarLabel"
            :show-search="mode === 'schedule'"
            :transparent="true"
            @logo-click="handleLogoClick"
        />

        <!-- EMPTY STATE: No active schedules in database -->
        <main
            v-if="schedules.length === 0"
            class="relative z-10 mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center px-6 pt-20 text-center"
        >
            <div
                class="flex size-16 items-center justify-center rounded-2xl border border-white/15 bg-[#18191d]"
            >
                <CalendarDays class="size-8 text-[#f5b82e]" />
            </div>
            <h1
                class="mt-6 font-display text-2xl font-bold tracking-tight sm:text-3xl"
            >
                Belum ada jadwal tayang aktif
            </h1>
            <p class="mt-3 max-w-md text-sm leading-relaxed text-[#9ca0aa]">
                Jadwal pemutaran film sedang disiapkan oleh pengelola bioskop.
                Silakan periksa kembali beberapa saat lagi.
            </p>
            <Link
                href="/movies"
                class="mt-7 inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#f5b82e] px-6 text-sm font-bold text-[#121212] transition hover:bg-[#ffc542]"
            >
                <Clapperboard class="size-4" />
                <span>Lihat Katalog Film</span>
            </Link>
        </main>

        <!-- EMPTY STATE: Filters returned 0 results -->
        <main
            v-else-if="movieGroups.length === 0"
            class="relative z-10 mx-auto flex min-h-screen max-w-2xl flex-col items-center justify-center px-6 pt-20 text-center"
        >
            <div
                class="flex size-16 items-center justify-center rounded-2xl border border-white/15 bg-[#18191d]"
            >
                <Clapperboard class="size-8 text-[#f5b82e]" />
            </div>
            <h1 class="mt-6 font-display text-2xl font-bold tracking-tight">
                Tidak ada jadwal yang cocok
            </h1>
            <p class="mt-3 text-sm text-[#9ca0aa]">
                Coba ganti filter cabang bioskop, tanggal, atau kata kunci
                pencarian Anda.
            </p>
            <button
                type="button"
                class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-[#f5b82e] px-6 text-sm font-bold text-[#121212] transition hover:bg-[#ffc542]"
                @click="resetFilters"
            >
                Tampilkan Semua Jadwal
            </button>
        </main>

        <!-- STAGE MODES -->
        <template v-else-if="activeMovie && activeSchedule">
            <!-- ========================================================= -->
            <!-- MODE 1: CLEAN SINGLE-SCREEN HERO CAROUSEL                 -->
            <!-- ========================================================= -->
            <div
                v-if="mode === 'schedule'"
                ref="scheduleStageRef"
                class="relative z-10"
            >
                <section
                    class="mx-auto flex min-h-screen max-w-[1400px] flex-col justify-between px-5 pt-24 pb-10 sm:px-8 lg:px-12 lg:pt-28 lg:pb-12"
                >
                    <!-- Interactive Date Filter Strip -->
                    <div
                        class="flex flex-wrap items-center gap-2 border-b border-white/10 pb-4"
                        role="tablist"
                        aria-label="Filter tanggal tayang"
                    >
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="selectedDateFilter === 'all'"
                            :class="[
                                'inline-flex min-h-8 items-center gap-2 rounded-full border px-3.5 py-1 text-xs font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]',
                                selectedDateFilter === 'all'
                                    ? 'border-[#f5b82e] bg-[#f5b82e]/15 text-[#f5b82e]'
                                    : 'border-white/15 bg-white/5 text-white/80 hover:border-white/35 hover:text-white',
                            ]"
                            @click="selectedDateFilter = 'all'"
                        >
                            Semua Tanggal
                        </button>
                        <button
                            v-for="d in availableDates"
                            :key="d.iso"
                            type="button"
                            role="tab"
                            :aria-selected="selectedDateFilter === d.iso"
                            :class="[
                                'inline-flex min-h-8 items-center gap-2 rounded-full border px-3.5 py-1 text-xs font-medium tabular-nums transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]',
                                selectedDateFilter === d.iso
                                    ? 'border-[#f5b82e] bg-[#f5b82e]/15 font-semibold text-[#f5b82e]'
                                    : 'border-white/15 bg-white/5 text-white/80 hover:border-white/35 hover:text-white',
                            ]"
                            @click="selectedDateFilter = d.iso"
                        >
                            <span
                                v-if="selectedDateFilter === d.iso"
                                class="size-1.5 rounded-full bg-[#f5b82e]"
                            />
                            <span>{{ d.label }}</span>
                            <span class="text-[11px] opacity-65">{{
                                d.weekday
                            }}</span>
                        </button>
                    </div>

                    <!-- Split Showcase: Left Active Movie + Center (<)(>) + Right Poster Queue -->
                    <div
                        class="mt-8 grid items-end gap-8 lg:mt-auto lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1.15fr)] lg:gap-6"
                    >
                        <!-- Left Column -->
                        <div class="max-w-xl">
                            <div class="relative mb-5 pl-4">
                                <span
                                    class="absolute top-1.5 left-0 size-2 rounded-full bg-white shadow-[0_0_10px_rgba(255,255,255,0.9)]"
                                />
                                <span
                                    class="absolute top-3.5 left-[3.5px] h-11 w-px bg-gradient-to-b from-white/55 to-transparent"
                                />
                                <div
                                    class="relative h-7 overflow-hidden font-display text-lg font-semibold text-white"
                                >
                                    <span
                                        v-show="
                                            isCarouselTransitioning &&
                                            outgoingDayLabel &&
                                            outgoingDayLabel !== activeDateLabel
                                        "
                                        ref="outgoingDayRef"
                                        class="absolute inset-y-0 left-0 flex items-center"
                                    >
                                        {{ outgoingDayLabel }}
                                    </span>
                                    <span
                                        ref="scheduleDayRef"
                                        class="flex items-center gap-2"
                                    >
                                        <span>{{ activeDateLabel }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Split Odometer Numeral + Clipped Title -->
                            <div class="flex items-start gap-4 sm:gap-5">
                                <div
                                    ref="heroNumberWrapRef"
                                    aria-hidden="true"
                                    class="flex shrink-0 items-baseline font-display text-6xl leading-none font-extralight tracking-tight text-white/90 tabular-nums select-none sm:text-7xl lg:text-[5.25rem]"
                                >
                                    <span
                                        class="relative inline-block h-[1em] overflow-hidden"
                                    >
                                        <span
                                            v-show="
                                                isCarouselTransitioning &&
                                                outgoingDigitTens !==
                                                    currentDigitTens
                                            "
                                            ref="outgoingTensRef"
                                            class="astral-outline-num absolute inset-0 block"
                                        >
                                            {{ outgoingDigitTens }}
                                        </span>
                                        <span
                                            ref="incomingTensRef"
                                            class="astral-outline-num block"
                                        >
                                            {{ currentDigitTens }}
                                        </span>
                                    </span>
                                    <span
                                        class="relative inline-block h-[1em] overflow-hidden"
                                    >
                                        <span
                                            v-show="isCarouselTransitioning"
                                            ref="outgoingOnesRef"
                                            class="astral-outline-num absolute inset-0 block will-change-transform"
                                        >
                                            {{ outgoingDigitOnes }}
                                        </span>
                                        <span
                                            ref="incomingOnesRef"
                                            class="astral-outline-num block will-change-transform"
                                        >
                                            {{ currentDigitOnes }}
                                        </span>
                                    </span>
                                </div>

                                <div class="min-w-0 flex-1 overflow-hidden">
                                    <div class="relative overflow-hidden">
                                        <p
                                            v-show="isCarouselTransitioning"
                                            ref="outgoingTitleRef"
                                            aria-hidden="true"
                                            class="pointer-events-none absolute inset-x-0 top-0 font-display text-3xl leading-[1.08] font-bold tracking-tight text-white will-change-transform sm:text-5xl"
                                        >
                                            {{ outgoingTitle }}
                                        </p>
                                        <h1
                                            ref="scheduleTitleRef"
                                            class="font-display text-3xl leading-[1.08] font-bold tracking-tight text-white will-change-transform sm:text-5xl"
                                        >
                                            {{ activeMovie.title }}
                                        </h1>
                                    </div>

                                    <div
                                        class="relative mt-3.5 overflow-hidden"
                                    >
                                        <p
                                            v-show="isCarouselTransitioning"
                                            ref="outgoingMetaRef"
                                            aria-hidden="true"
                                            class="pointer-events-none absolute inset-x-0 top-0 truncate text-xs text-[#9ca0aa] will-change-transform"
                                        >
                                            <span class="text-white/80"
                                                >Genre:</span
                                            >
                                            {{ outgoingGenre }}
                                        </p>
                                        <div
                                            ref="scheduleMetaRef"
                                            class="flex flex-wrap items-center gap-2.5 text-xs text-[#9ca0aa] will-change-transform"
                                        >
                                            <span v-if="activeMovie.genre">
                                                <strong
                                                    class="font-semibold text-white/90"
                                                    >Genre:</strong
                                                >
                                                {{ activeMovie.genre }}
                                            </span>
                                            <span
                                                class="rounded border border-white/25 px-2 py-0.5 text-[10px] font-medium tracking-wider text-white/85 uppercase"
                                            >
                                                {{ activeSchedule.studio_name }}
                                            </span>
                                            <span
                                                v-if="activeMovie.rating_age"
                                                class="rounded border border-white/25 px-2 py-0.5 text-[10px] font-semibold text-white/90 uppercase"
                                            >
                                                {{ activeMovie.rating_age }}
                                            </span>
                                            <span
                                                v-if="
                                                    activeMovie.duration_minute
                                                "
                                                class="inline-flex items-center gap-1 rounded border border-white/20 px-2 py-0.5 text-[10px] font-medium text-white/80 tabular-nums"
                                            >
                                                <Clock3
                                                    class="size-3 text-[#f5b82e]"
                                                />
                                                {{
                                                    activeMovie.duration_minute
                                                }}
                                                mnt
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Session Schedule Pills -->
                            <div class="mt-7">
                                <div
                                    class="flex items-center justify-between gap-2 text-xs text-[#9ca0aa]"
                                >
                                    <span class="font-medium text-white/85"
                                        >Session schedule:</span
                                    >
                                    <span
                                        class="font-semibold text-[#f5b82e] tabular-nums"
                                    >
                                        {{ money(activeSchedule.price) }}
                                        <span class="font-normal text-[#9ca0aa]"
                                            >/ kursi</span
                                        >
                                    </span>
                                </div>

                                <div
                                    ref="schedulePillsTrackRef"
                                    class="mt-3 flex flex-wrap gap-2.5 overflow-hidden py-1"
                                >
                                    <button
                                        v-for="slot in activeMovie.schedules"
                                        :key="slot.id_schedule"
                                        data-time-pill
                                        type="button"
                                        :aria-pressed="
                                            activeSchedule.id_schedule ===
                                            slot.id_schedule
                                        "
                                        :class="[
                                            'inline-flex min-h-9 items-center justify-center rounded-full border px-4 py-1 text-xs font-semibold tabular-nums transition duration-200 will-change-transform focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]',
                                            activeSchedule.id_schedule ===
                                            slot.id_schedule
                                                ? 'border-[#f5b82e] bg-[#f5b82e]/15 text-white shadow-[0_0_16px_rgba(245,184,46,0.28)]'
                                                : 'border-white/25 bg-black/25 text-white/80 hover:border-white/50 hover:text-white',
                                        ]"
                                        @click="
                                            selectedScheduleId =
                                                slot.id_schedule
                                        "
                                    >
                                        {{ slot.show_time.slice(0, 5) }}
                                    </button>
                                </div>
                            </div>

                            <!-- Primary CTA Row: Book a session + More -->
                            <div
                                ref="heroCtaRowRef"
                                class="mt-8 flex flex-wrap items-center gap-3.5"
                            >
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center justify-center gap-2.5 rounded bg-[#f5b82e] px-6 text-xs font-bold tracking-wide text-[#121212] shadow-[0_8px_28px_rgba(245,184,46,0.28)] transition hover:bg-[#ffc542] active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                                    @click="enterFilmPage(true, false, true)"
                                >
                                    <Ticket class="size-4" />
                                    <span>Book a session</span>
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center justify-center gap-2.5 rounded border border-white/30 bg-black/20 px-5 text-xs font-medium text-white transition hover:border-white/60 hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                                    @click="enterFilmPage(false, false, true)"
                                >
                                    <span>More</span>
                                    <Play class="size-3 fill-current" />
                                </button>
                            </div>
                        </div>

                        <!-- Center Column: (<) (>) Circular Controls -->
                        <div
                            v-if="movieGroups.length > 1"
                            ref="heroNavControlsRef"
                            class="flex items-center gap-3 pb-1"
                        >
                            <button
                                type="button"
                                aria-label="Film sebelumnya"
                                class="group relative inline-flex size-10 items-center justify-center rounded-full border border-white/25 bg-black/30 text-white/80 transition hover:border-white/60 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                                @click="prevMovie"
                            >
                                <svg
                                    class="pointer-events-none absolute -inset-0.5 size-11 -rotate-90"
                                    viewBox="0 0 48 48"
                                    fill="none"
                                >
                                    <circle
                                        ref="prevRingCircleRef"
                                        cx="24"
                                        cy="24"
                                        r="22"
                                        stroke="white"
                                        stroke-width="1.5"
                                        stroke-dasharray="138"
                                        stroke-dashoffset="138"
                                        class="opacity-0"
                                    />
                                </svg>
                                <ChevronLeft class="size-4" />
                            </button>

                            <button
                                type="button"
                                aria-label="Film berikutnya"
                                class="group relative inline-flex size-12 items-center justify-center rounded-full border border-white/30 bg-black/35 text-white transition hover:border-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]"
                                @click="nextMovie"
                            >
                                <span
                                    class="pointer-events-none absolute -top-0.5 left-1/2 size-1.5 -translate-x-1/2 rounded-full bg-white shadow-[0_0_8px_#ffffff]"
                                />
                                <svg
                                    class="pointer-events-none absolute inset-0 size-full -rotate-90"
                                    viewBox="0 0 48 48"
                                    fill="none"
                                >
                                    <circle
                                        ref="nextRingCircleRef"
                                        cx="24"
                                        cy="24"
                                        r="22"
                                        stroke="white"
                                        stroke-width="1.75"
                                        stroke-linecap="round"
                                        stroke-dasharray="138"
                                        stroke-dashoffset="138"
                                    />
                                </svg>
                                <ChevronRight
                                    class="size-5 transition-transform duration-200 group-hover:translate-x-0.5"
                                />
                            </button>
                        </div>

                        <!-- Right Column: Poster Queue -->
                        <div class="min-w-0 overflow-hidden">
                            <div
                                ref="carouselTrackRef"
                                class="flex gap-5 overflow-hidden pt-10 pb-2"
                            >
                                <button
                                    v-for="item in queuedMovies"
                                    :key="`queue-${item.movie.key}`"
                                    data-poster-card
                                    type="button"
                                    class="group relative flex w-40 shrink-0 flex-col text-left will-change-transform focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#f5b82e] sm:w-44"
                                    @click="
                                        selectMovieFromCard(item.originalIndex)
                                    "
                                >
                                    <div
                                        v-if="item.showDayHeader"
                                        class="pointer-events-none absolute -top-9 left-0 flex items-center gap-2"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-white shadow-[0_0_8px_#ffffff]"
                                        />
                                        <span
                                            class="font-display text-sm font-semibold text-white/95"
                                        >
                                            {{ item.movie.dayGroupLabel }}
                                        </span>
                                    </div>
                                    <span
                                        v-if="item.showDayHeader"
                                        aria-hidden="true"
                                        class="pointer-events-none absolute -top-7 bottom-0 -left-2.5 w-px bg-gradient-to-b from-white/50 via-white/15 to-transparent"
                                    />

                                    <span
                                        class="mb-2.5 font-display text-xs font-medium text-white/60 tabular-nums transition-colors group-hover:text-[#f5b82e]"
                                    >
                                        {{ item.displayNumber }}
                                    </span>

                                    <div
                                        class="relative aspect-[2/3] w-full overflow-hidden rounded-md border border-white/10 bg-[#18191d] shadow-[0_14px_34px_rgba(0,0,0,0.65)] transition-all duration-300 group-hover:-translate-y-1.5 group-hover:border-[#f5b82e]/70"
                                    >
                                        <img
                                            v-if="item.movie.poster"
                                            :src="item.movie.poster"
                                            :alt="`Poster ${item.movie.title}`"
                                            loading="lazy"
                                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-[#1e2025]"
                                        >
                                            <Clapperboard
                                                class="size-8 text-white/25"
                                            />
                                        </div>

                                        <div
                                            class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#121212] via-[#121212]/80 to-transparent p-3.5 pt-12 text-center"
                                        >
                                            <p
                                                class="line-clamp-2 font-display text-xs leading-snug font-bold text-white sm:text-sm"
                                            >
                                                {{ item.movie.title }}
                                            </p>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ========================================================================= -->
            <!-- MODE 2 & 3 UNIFIED: SINGLE SCROLLABLE FLOW (Detail Top + Seat Selection) -->
            <!-- ========================================================================= -->
            <div v-else ref="filmStageRef" class="relative z-10">
                <!-- TOP SECTION: Movie Details, Cast Portraits, Storyline & Trailer -->
                <section
                    class="mx-auto flex min-h-screen w-full max-w-[1400px] flex-col justify-center px-5 pt-28 pb-14 sm:px-8 lg:px-12"
                >
                    <div
                        class="grid items-center gap-10 lg:grid-cols-[minmax(0,1fr)_auto_minmax(0,1.08fr)] lg:gap-12"
                    >
                        <!-- Left Column: Title + Metadata (Cinema is in Top Navbar) + Order Prompt -->
                        <div class="max-w-lg">
                            <h1
                                ref="detailTitleRef"
                                class="font-display text-3xl leading-[1.08] font-bold tracking-tight text-white will-change-transform sm:text-5xl"
                            >
                                {{ activeMovie.title }}
                            </h1>

                            <dl
                                ref="detailSpecsRef"
                                class="mt-7 grid grid-cols-[105px_1fr] gap-y-2.5 text-xs sm:text-[13px]"
                            >
                                <template v-if="activeShowcase.grade">
                                    <dt
                                        data-spec-row
                                        class="font-medium text-white/60"
                                    >
                                        Grade:
                                    </dt>
                                    <dd
                                        data-spec-row
                                        class="flex items-center gap-2 font-semibold text-white tabular-nums"
                                    >
                                        <span
                                            class="inline-flex items-center gap-0.5 text-[#f5b82e]"
                                        >
                                            <Star
                                                v-for="s in 5"
                                                :key="s"
                                                class="size-3.5"
                                                :class="
                                                    s <= filledStars
                                                        ? 'fill-[#f5b82e] text-[#f5b82e]'
                                                        : 'text-[#f5b82e]/35'
                                                "
                                            />
                                        </span>
                                        <span class="text-white/90">{{
                                            activeShowcase.grade
                                        }}</span>
                                    </dd>
                                </template>

                                <template v-if="activeMovie.rating_age">
                                    <dt
                                        data-spec-row
                                        class="font-medium text-white/60"
                                    >
                                        Rating:
                                    </dt>
                                    <dd
                                        data-spec-row
                                        class="font-medium text-white/95 uppercase"
                                    >
                                        {{ activeMovie.rating_age }}
                                    </dd>
                                </template>

                                <dt
                                    data-spec-row
                                    class="font-medium text-white/60"
                                >
                                    Format:
                                </dt>
                                <dd
                                    data-spec-row
                                    class="font-medium text-white/95 lowercase"
                                >
                                    {{ activeSchedule.studio_name }}
                                </dd>

                                <template v-if="activeMovie.genre">
                                    <dt
                                        data-spec-row
                                        class="font-medium text-white/60"
                                    >
                                        Genre:
                                    </dt>
                                    <dd
                                        data-spec-row
                                        class="font-medium text-white/95"
                                    >
                                        {{ activeMovie.genre }}
                                    </dd>
                                </template>

                                <template v-if="activeShowcase.country">
                                    <dt
                                        data-spec-row
                                        class="font-medium text-white/60"
                                    >
                                        Country:
                                    </dt>
                                    <dd
                                        data-spec-row
                                        class="font-medium text-white/95"
                                    >
                                        {{ activeShowcase.country }}
                                    </dd>
                                </template>

                                <dt
                                    data-spec-row
                                    class="font-medium text-white/60"
                                >
                                    Duration:
                                </dt>
                                <dd
                                    data-spec-row
                                    class="font-medium text-white/95 tabular-nums"
                                >
                                    {{ activeMovie.duration_minute }} min.
                                </dd>

                                <template v-if="activeShowcase.language">
                                    <dt
                                        data-spec-row
                                        class="font-medium text-white/60"
                                    >
                                        Tongue:
                                    </dt>
                                    <dd
                                        data-spec-row
                                        class="font-medium text-white/95"
                                    >
                                        {{ activeShowcase.language }}
                                    </dd>
                                </template>

                                <template v-if="activeShowcase.director">
                                    <dt
                                        data-spec-row
                                        class="font-medium text-white/60"
                                    >
                                        Director:
                                    </dt>
                                    <dd
                                        data-spec-row
                                        class="font-medium text-white/95"
                                    >
                                        {{ activeShowcase.director }}
                                    </dd>
                                </template>
                            </dl>

                            <button
                                ref="detailOrderBtnRef"
                                type="button"
                                class="group mt-10 inline-flex flex-col items-center gap-1.5 text-xs font-medium text-white/70 transition hover:text-[#f5b82e] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#f5b82e]"
                                @click="scrollToBookingSection(false)"
                            >
                                <span>Order a ticket right now</span>
                                <ChevronDown
                                    class="size-4 transition-transform duration-300 group-hover:translate-y-1"
                                />
                            </button>
                        </div>

                        <!-- Center Column: Play Trailer Button -->
                        <div class="flex items-center justify-center">
                            <button
                                ref="detailPlayBtnRef"
                                type="button"
                                :aria-label="
                                    resolvedTrailerUrl
                                        ? `Tonton trailer ${activeMovie.title}`
                                        : 'Pilih kursi sekarang'
                                "
                                class="group relative flex size-16 items-center justify-center rounded-full bg-white text-[#121212] ring-[10px] ring-white/20 transition duration-300 hover:scale-105 hover:bg-[#f5b82e] hover:ring-[#f5b82e]/30 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#f5b82e]"
                                @click="
                                    resolvedTrailerUrl
                                        ? openTrailerModal()
                                        : scrollToBookingSection(false)
                                "
                            >
                                <Play
                                    class="size-5 translate-x-0.5 fill-current"
                                />
                            </button>
                        </div>

                        <!-- Right Column: Portrait Starring Cast Cards + Storyline -->
                        <div class="min-w-0 space-y-7">
                            <div v-if="castList.length > 0" ref="detailCastRef">
                                <h2
                                    class="font-display text-sm font-semibold text-white/95"
                                >
                                    Starring:
                                </h2>

                                <div
                                    ref="castTrackRef"
                                    class="astral-no-scrollbar mt-3.5 flex gap-3.5 overflow-x-auto pb-1"
                                >
                                    <article
                                        v-for="(actor, idx) in castList"
                                        :key="`${actor.name}-${idx}`"
                                        :data-cast-idx="idx"
                                        data-cast-card
                                        class="group relative aspect-[3/4] w-28 shrink-0 overflow-hidden rounded-md bg-[#18191d] shadow-lg sm:w-32"
                                    >
                                        <img
                                            v-if="actor.photo"
                                            :src="actor.photo"
                                            :alt="actor.name"
                                            loading="lazy"
                                            class="h-full w-full object-cover object-top transition-transform duration-500 group-hover:scale-105"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-[#202227]"
                                        >
                                            <User
                                                class="size-7 text-white/25"
                                            />
                                        </div>

                                        <div
                                            class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/95 via-black/60 to-transparent p-2.5 pt-10"
                                        >
                                            <p
                                                class="line-clamp-2 text-[11px] leading-tight font-semibold text-white"
                                            >
                                                {{ actor.name }}
                                            </p>
                                        </div>
                                    </article>
                                </div>

                                <div
                                    v-if="castList.length > 3"
                                    class="mt-4 flex items-center gap-4"
                                >
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            aria-label="Pemeran sebelumnya"
                                            class="inline-flex size-8 items-center justify-center rounded-full border border-white/25 bg-black/25 text-white/75 transition hover:border-white hover:text-white"
                                            @click="slideCast(-1)"
                                        >
                                            <ChevronLeft class="size-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            aria-label="Pemeran berikutnya"
                                            class="inline-flex size-8 items-center justify-center rounded-full border border-white/25 bg-black/25 text-white/75 transition hover:border-white hover:text-white"
                                            @click="slideCast(1)"
                                        >
                                            <ChevronRight class="size-3.5" />
                                        </button>
                                    </div>

                                    <div
                                        class="relative h-px flex-1 bg-white/20"
                                        aria-hidden="true"
                                    >
                                        <div
                                            class="absolute inset-y-0 left-0 h-0.5 -translate-y-1/4 bg-white transition-all duration-300"
                                            :style="{
                                                width: `${castProgressPercent}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div ref="detailStoryRef">
                                <h2
                                    class="font-display text-sm font-semibold text-white/95"
                                >
                                    Storyline:
                                </h2>
                                <p
                                    class="mt-3 max-w-[54ch] text-xs leading-relaxed text-[#9ca0aa] sm:text-[13px]"
                                >
                                    {{
                                        resolvedSynopsis ??
                                        `Saksikan penayangan ${activeMovie.title} di ${activeSchedule.cinema.name} (${activeSchedule.studio_name}). Gulir ke bawah untuk memilih jadwal dan kursi.`
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- BOTTOM SECTION: 2-Stage Ticket & Seat Selection (Scrollable below Detail) -->
                <main
                    id="booking-section"
                    ref="bookingSectionRef"
                    class="mx-auto min-h-[90svh] max-w-[1400px] scroll-mt-24 border-t border-white/10 px-5 pt-10 pb-20 sm:px-8 lg:px-12 lg:pt-14"
                >
                    <div
                        class="grid items-start gap-12 lg:grid-cols-[400px_minmax(0,1fr)] xl:grid-cols-[420px_minmax(0,1fr)] xl:gap-16"
                    >
                        <!-- LEFT COLUMN: 2-Stage Date Collapse + Time Pills + Expandable Tickets Card -->
                        <div class="space-y-6">
                            <!-- 1. Collapsible Date Strip -->
                            <div
                                class="grid transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                :style="{
                                    gridTemplateRows: dateStripCollapsed
                                        ? '0fr'
                                        : '1fr',
                                    opacity: dateStripCollapsed ? 0 : 1,
                                    marginBottom: dateStripCollapsed
                                        ? '-1.5rem'
                                        : '0px',
                                }"
                            >
                                <div class="overflow-hidden">
                                    <div class="pb-2">
                                        <h2
                                            class="font-display text-sm font-semibold text-white/95"
                                        >
                                            Date for {{ currentMonthName }}:
                                        </h2>

                                        <div class="mt-4 grid grid-cols-5 gap-2">
                                            <button
                                                v-for="d in bookingDateStrip"
                                                :key="d.iso"
                                                type="button"
                                                :disabled="!d.hasSchedule"
                                                :aria-pressed="
                                                    d.iso === currentShowDateIso
                                                "
                                                :class="[
                                                    'flex flex-col items-center justify-center rounded border py-2.5 transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]',
                                                    d.iso === currentShowDateIso
                                                        ? 'border-[#f5b82e] bg-[#f5b82e]/10 text-white shadow-[0_0_18px_rgba(245,184,46,0.2)]'
                                                        : d.hasSchedule
                                                          ? 'border-white/15 bg-white/[0.02] text-white/85 hover:border-white/40 hover:text-white'
                                                          : 'cursor-not-allowed border-transparent text-white/25',
                                                ]"
                                                @click="
                                                    pickBookingDate(
                                                        d.firstScheduleId,
                                                    )
                                                "
                                            >
                                                <span
                                                    class="font-display text-3xl leading-none font-bold tabular-nums sm:text-[2.15rem]"
                                                >
                                                    {{ d.dayNum }}
                                                </span>
                                                <span
                                                    class="mt-1.5 text-[10px] font-semibold tracking-widest uppercase"
                                                    :class="
                                                        d.iso ===
                                                        currentShowDateIso
                                                            ? 'text-[#f5b82e]'
                                                            : 'text-[#9ca0aa]'
                                                    "
                                                >
                                                    {{ d.weekday }}
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Time Selector Row -->
                            <div>
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <h2
                                        class="font-display text-sm font-semibold text-white/95"
                                    >
                                        Time:
                                    </h2>
                                    <button
                                        v-if="hasPickedTime"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-[#f5b82e] transition hover:underline"
                                        @click="reopenDateStrip"
                                    >
                                        <Calendar class="size-3" />
                                        <span>{{
                                            dateStripCollapsed
                                                ? `Ganti tanggal (${formattedDotDate})`
                                                : 'Tutup tanggal'
                                        }}</span>
                                    </button>
                                </div>

                                <div class="mt-3.5 flex flex-wrap gap-2.5">
                                    <button
                                        v-for="slot in timeSlotsForBookingDate"
                                        :key="slot.id_schedule"
                                        type="button"
                                        :aria-pressed="
                                            hasPickedTime &&
                                            slot.id_schedule ===
                                                activeSchedule.id_schedule
                                        "
                                        :class="[
                                            'inline-flex min-h-9 items-center justify-center rounded-full border px-4 py-1 text-xs tabular-nums transition duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e]',
                                            hasPickedTime &&
                                            slot.id_schedule ===
                                                activeSchedule.id_schedule
                                                ? 'border-[#f5b82e] bg-[#f5b82e] font-bold text-[#121212] shadow-[0_0_20px_rgba(245,184,46,0.38)]'
                                                : 'border-white/25 bg-white/[0.03] font-semibold text-white/80 hover:border-[#f5b82e]/70 hover:text-white',
                                        ]"
                                        @click="pickTimeSlot(slot)"
                                    >
                                        {{ slot.show_time.slice(0, 5) }}
                                    </button>
                                </div>
                            </div>

                            <!-- 3. Tickets Card -->
                            <div>
                                <h2
                                    class="font-display text-sm font-semibold text-white/95"
                                >
                                    Tickets:
                                </h2>

                                <aside
                                    ref="ticketCardRef"
                                    aria-label="Ringkasan tiket terpilih"
                                    class="mt-3.5 rounded-md border border-white/10 bg-[#1b1c20] p-5 shadow-[0_20px_50px_rgba(0,0,0,0.65)] transition-all duration-500 sm:p-6"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <h3
                                            class="font-display text-xl font-bold text-white"
                                        >
                                            {{ activeMovie.title }}
                                        </h3>
                                        <button
                                            type="button"
                                            :aria-label="
                                                ticketCardExpanded
                                                    ? 'Sembunyikan rincian kursi'
                                                    : 'Tampilkan rincian kursi'
                                            "
                                            class="rounded p-1 text-white/70 transition hover:bg-white/10 hover:text-white"
                                            @click="
                                                ticketCardExpanded =
                                                    !ticketCardExpanded
                                            "
                                        >
                                            <ChevronUp
                                                v-if="ticketCardExpanded"
                                                class="size-4"
                                            />
                                            <ChevronDown
                                                v-else
                                                class="size-4"
                                            />
                                        </button>
                                    </div>

                                    <div
                                        class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-[#9ca0aa] tabular-nums"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Clock3
                                                class="size-3.5 text-white/60"
                                            />
                                            {{
                                                hasPickedTime
                                                    ? activeSchedule.show_time.slice(
                                                          0,
                                                          5,
                                                      )
                                                    : '--:--'
                                            }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Calendar
                                                class="size-3.5 text-white/60"
                                            />
                                            {{ formattedDotDate }}
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <MapPin
                                                class="size-3.5 text-white/60"
                                            />
                                            {{ activeSchedule.cinema.name }}, "{{
                                                activeSchedule.studio_name
                                            }}"
                                        </span>
                                    </div>

                                    <!-- Expandable Body -->
                                    <div
                                        class="grid transition-all duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                        :style="{
                                            gridTemplateRows: ticketCardExpanded
                                                ? '1fr'
                                                : '0fr',
                                            opacity: ticketCardExpanded ? 1 : 0,
                                        }"
                                    >
                                        <div class="overflow-hidden">
                                            <div class="mt-5">
                                                <div
                                                    class="grid grid-cols-[0.9fr_1.1fr_0.9fr_0.8fr_1.3fr] items-center rounded bg-[#121212] px-3 py-2 text-[10px] font-semibold tracking-wider text-[#9ca0aa] uppercase"
                                                >
                                                    <span>KURSI</span>
                                                    <span>STUDIO</span>
                                                    <span>BARIS</span>
                                                    <span>NOMOR</span>
                                                    <span class="text-right"
                                                        >HARGA</span
                                                    >
                                                </div>

                                                <div
                                                    v-if="
                                                        selectedSeatCells.length ===
                                                        0
                                                    "
                                                    class="flex min-h-36 flex-col items-center justify-center py-8 text-center"
                                                >
                                                    <p
                                                        class="inline-flex items-center gap-2 font-display text-lg font-medium text-white/30"
                                                    >
                                                        <span
                                                            >Choose a
                                                            place</span
                                                        >
                                                        <ArrowRight
                                                            class="size-4"
                                                        />
                                                    </p>
                                                    <p
                                                        class="mt-1.5 text-xs text-[#9ca0aa]"
                                                    >
                                                        Klik kursi pada denah
                                                        (maks.
                                                        {{ maxSeats }} kursi)
                                                    </p>
                                                </div>

                                                <div
                                                    v-else
                                                    class="mt-1 divide-y divide-white/8"
                                                >
                                                    <div
                                                        v-for="cell in selectedSeatCells"
                                                        :key="cell.seatNumber"
                                                        :data-seat-row="
                                                            cell.seatNumber
                                                        "
                                                        class="group grid grid-cols-[0.9fr_1.1fr_0.9fr_0.8fr_1.3fr] items-center px-3 py-3 text-xs tabular-nums transition hover:bg-white/[0.03]"
                                                    >
                                                        <span>
                                                            <span
                                                                class="inline-flex items-center justify-center rounded bg-[#f5b82e]/15 px-2 py-0.5 font-display text-xs font-bold text-[#f5b82e]"
                                                            >
                                                                {{
                                                                    cell.seatCode
                                                                }}
                                                            </span>
                                                        </span>
                                                        <span
                                                            class="truncate text-white/85"
                                                        >
                                                            {{
                                                                activeSchedule.studio_name
                                                            }}
                                                        </span>
                                                        <span
                                                            class="font-medium text-white/90"
                                                        >
                                                            Baris
                                                            {{ cell.rowLetter }}
                                                        </span>
                                                        <span
                                                            class="text-white/85"
                                                        >
                                                            No.
                                                            {{ cell.colNumber }}
                                                        </span>
                                                        <span
                                                            class="flex items-center justify-end gap-1.5 font-bold text-white"
                                                        >
                                                            <span>{{
                                                                money(
                                                                    activeSchedule.price,
                                                                )
                                                            }}</span>
                                                            <button
                                                                type="button"
                                                                :aria-label="`Batal pilih kursi ${cell.seatCode}`"
                                                                class="rounded p-0.5 text-white/40 opacity-75 transition hover:bg-white/10 hover:text-[#f5b82e] group-hover:opacity-100"
                                                                @click="
                                                                    toggleSeat(
                                                                        cell.seatNumber,
                                                                    )
                                                                "
                                                            >
                                                                <X
                                                                    class="size-3"
                                                                />
                                                            </button>
                                                        </span>
                                                    </div>
                                                </div>

                                                <p
                                                    v-if="form.errors.seats"
                                                    role="alert"
                                                    class="mt-3 rounded border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs text-red-200"
                                                >
                                                    {{ form.errors.seats }}
                                                </p>
                                                <p
                                                    v-if="form.errors['seats.0']"
                                                    role="alert"
                                                    class="mt-3 rounded border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs text-red-200"
                                                >
                                                    {{ form.errors['seats.0'] }}
                                                </p>

                                                <div
                                                    class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-white/10 pt-5"
                                                >
                                                    <button
                                                        type="button"
                                                        :disabled="
                                                            form.seats
                                                                .length === 0 ||
                                                            form.processing
                                                        "
                                                        class="inline-flex min-h-11 items-center justify-center gap-2 rounded bg-[#f5b82e] px-6 text-xs font-bold tracking-wide text-[#121212] shadow-[0_8px_24px_rgba(245,184,46,0.28)] transition hover:bg-[#ffc542] active:scale-[0.98] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e] disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
                                                        @click="holdSeats"
                                                    >
                                                        <Ticket
                                                            class="size-4"
                                                        />
                                                        <span>{{
                                                            form.processing
                                                                ? 'Memproses...'
                                                                : 'To pay'
                                                        }}</span>
                                                    </button>

                                                    <div class="text-right">
                                                        <span
                                                            class="text-xs font-medium text-white/80"
                                                            >Total:
                                                        </span>
                                                        <span
                                                            class="font-display text-base font-bold text-[#f5b82e] tabular-nums"
                                                        >
                                                            {{
                                                                money(
                                                                    animatedPrice,
                                                                )
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <p
                                                    class="mt-4 flex items-center gap-1.5 text-[11px] text-[#9ca0aa]"
                                                >
                                                    <Info
                                                        class="size-3.5 shrink-0 text-[#f5b82e]"
                                                    />
                                                    <span>
                                                        Kursi ditahan
                                                        {{ holdMinutes }} menit
                                                        setelah dikunci untuk
                                                        pembayaran Midtrans.
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </aside>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: Luminous Curved Screen + 2-Stage Seat Map -->
                        <section
                            aria-labelledby="auditorium-heading"
                            class="relative flex flex-col items-center pt-2"
                        >
                            <h2 id="auditorium-heading" class="sr-only">
                                Denah Tempat Duduk Auditorium
                            </h2>

                            <div
                                ref="screenArcRef"
                                :class="[
                                    'relative w-full max-w-[740px] transition-opacity duration-500 select-none',
                                    hasPickedTime
                                        ? 'opacity-100'
                                        : 'opacity-45',
                                ]"
                            >
                                <svg
                                    viewBox="0 0 800 135"
                                    fill="none"
                                    class="w-full overflow-visible"
                                    aria-hidden="true"
                                >
                                    <defs>
                                        <linearGradient
                                            id="astral-screen-curtain"
                                            x1="400"
                                            y1="14"
                                            x2="400"
                                            y2="132"
                                            gradientUnits="userSpaceOnUse"
                                        >
                                            <stop
                                                offset="0%"
                                                stop-color="#FFFFFF"
                                                stop-opacity="0.22"
                                            />
                                            <stop
                                                offset="42%"
                                                stop-color="#FFFFFF"
                                                stop-opacity="0.07"
                                            />
                                            <stop
                                                offset="100%"
                                                stop-color="#FFFFFF"
                                                stop-opacity="0"
                                            />
                                        </linearGradient>

                                        <radialGradient
                                            id="astral-screen-bloom"
                                            cx="50%"
                                            cy="18%"
                                            r="52%"
                                        >
                                            <stop
                                                offset="0%"
                                                stop-color="#FFFFFF"
                                                stop-opacity="0.24"
                                            />
                                            <stop
                                                offset="65%"
                                                stop-color="#FFFFFF"
                                                stop-opacity="0.04"
                                            />
                                            <stop
                                                offset="100%"
                                                stop-color="#FFFFFF"
                                                stop-opacity="0"
                                            />
                                        </radialGradient>

                                        <filter
                                            id="astral-curtain-blur"
                                            x="-10%"
                                            y="-20%"
                                            width="120%"
                                            height="140%"
                                        >
                                            <feGaussianBlur stdDeviation="7" />
                                        </filter>

                                        <filter
                                            id="astral-arc-glow"
                                            x="-10%"
                                            y="-50%"
                                            width="120%"
                                            height="200%"
                                        >
                                            <feGaussianBlur
                                                stdDeviation="8"
                                                result="blur1"
                                            />
                                            <feMerge>
                                                <feMergeNode in="blur1" />
                                                <feMergeNode
                                                    in="SourceGraphic"
                                                />
                                            </feMerge>
                                        </filter>
                                    </defs>

                                    <path
                                        d="M 38 48 Q 400 -2 762 48 L 796 130 Q 400 92 4 130 Z"
                                        fill="url(#astral-screen-curtain)"
                                        filter="url(#astral-curtain-blur)"
                                    />
                                    <ellipse
                                        cx="400"
                                        cy="58"
                                        rx="350"
                                        ry="52"
                                        fill="url(#astral-screen-bloom)"
                                    />
                                    <path
                                        d="M 34 48 Q 400 -12 766 48 Q 400 8 34 48 Z"
                                        fill="#FFFFFF"
                                        filter="url(#astral-arc-glow)"
                                    />
                                </svg>

                                <p
                                    class="-mt-16 text-center font-display text-xs font-medium tracking-wider text-white/85"
                                >
                                    Screen
                                </p>
                            </div>

                            <!-- Responsive Instruction Badge (mobile/tablet vs desktop) -->
                            <div
                                v-if="!hasPickedTime"
                                class="mt-6 rounded-full border border-[#f5b82e]/40 bg-[#18191d]/90 px-4 py-1.5 text-center text-xs font-medium text-[#f5b82e] shadow-lg"
                            >
                                <span class="lg:hidden"
                                    >Pilih jam tayang di atas untuk memilih
                                    kursi</span
                                >
                                <span class="hidden lg:inline"
                                    >Pilih jam tayang di sebelah kiri untuk
                                    memilih kursi</span
                                >
                            </div>
                            <div
                                v-else-if="loadingSeats"
                                class="mt-6 text-xs text-[#9ca0aa]"
                            >
                                Memperbarui ketersediaan kursi...
                            </div>

                            <!-- Tiered Theater Armchair Matrix -->
                            <div
                                ref="seatGridRef"
                                :class="[
                                    'astral-no-scrollbar w-full overflow-x-auto pt-8 pb-4 transition-all duration-500',
                                    hasPickedTime
                                        ? 'pointer-events-auto mt-4 opacity-100'
                                        : 'pointer-events-none mt-2 opacity-30 select-none',
                                ]"
                            >
                                <div
                                    class="mx-auto flex min-w-fit flex-col items-center gap-3 px-4"
                                >
                                    <div
                                        v-for="row in seatRows"
                                        :key="row.rowLetter"
                                        data-seat-row-el
                                        class="flex items-center justify-center gap-2 sm:gap-2.5"
                                    >
                                        <span
                                            aria-hidden="true"
                                            class="w-5 text-right font-display text-[11px] font-semibold text-white/30 select-none"
                                        >
                                            {{ row.rowLetter }}
                                        </span>

                                        <button
                                            v-for="cell in row.seats"
                                            :key="cell.seatNumber"
                                            type="button"
                                            :disabled="
                                                !hasPickedTime ||
                                                currentOccupiedSet.has(
                                                    cell.seatNumber,
                                                ) ||
                                                (form.seats.length >=
                                                    maxSeats &&
                                                    !form.seats.includes(
                                                        cell.seatNumber,
                                                    )) ||
                                                form.processing
                                            "
                                            :aria-label="`Kursi ${cell.seatNumber} (Baris ${cell.rowLetter} Nomor ${cell.colNumber}): ${currentOccupiedSet.has(cell.seatNumber) ? 'tidak tersedia' : form.seats.includes(cell.seatNumber) ? 'dipilih' : 'tersedia'}`"
                                            :aria-pressed="
                                                form.seats.includes(
                                                    cell.seatNumber,
                                                )
                                            "
                                            :class="[
                                                'group/seat relative flex size-8 items-center justify-center rounded transition-transform duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#f5b82e] sm:size-9',
                                                currentOccupiedSet.has(
                                                    cell.seatNumber,
                                                )
                                                    ? 'cursor-not-allowed text-[#2f3138]'
                                                    : form.seats.includes(
                                                            cell.seatNumber,
                                                        )
                                                      ? 'text-[#f5b82e] drop-shadow-[0_0_10px_rgba(245,184,46,0.55)]'
                                                      : 'text-[#787b84] hover:text-white disabled:cursor-not-allowed disabled:opacity-45',
                                            ]"
                                            @mouseenter="
                                                hoveredSeatNumber =
                                                    cell.seatNumber
                                            "
                                            @mouseleave="
                                                hoveredSeatNumber = null
                                            "
                                            @focus="
                                                hoveredSeatNumber =
                                                    cell.seatNumber
                                            "
                                            @blur="hoveredSeatNumber = null"
                                            @click="
                                                toggleSeat(
                                                    cell.seatNumber,
                                                    $event,
                                                )
                                            "
                                        >
                                            <span
                                                v-if="
                                                    hasPickedTime &&
                                                    hoveredSeatNumber ===
                                                        cell.seatNumber &&
                                                    !currentOccupiedSet.has(
                                                        cell.seatNumber,
                                                    )
                                                "
                                                aria-hidden="true"
                                                class="pointer-events-none absolute -top-8 left-1/2 z-20 -translate-x-1/2 rounded border border-white/20 bg-[#121212] px-2 py-0.5 font-display text-[10px] font-bold whitespace-nowrap text-white shadow-lg"
                                            >
                                                {{ cell.seatCode }}
                                            </span>

                                            <svg
                                                viewBox="0 0 28 26"
                                                fill="currentColor"
                                                class="size-full"
                                                aria-hidden="true"
                                            >
                                                <rect
                                                    x="6"
                                                    y="2"
                                                    width="16"
                                                    height="15"
                                                    rx="2.5"
                                                />
                                                <path
                                                    d="M2 5.5C2 4.12 3.12 3 4.5 3C5.33 3 6 3.67 6 4.5V16.5C6 17.6 6.9 18.5 8 18.5H20C21.1 18.5 22 17.6 22 16.5V4.5C22 3.67 22.67 3 23.5 3C24.88 3 26 4.12 26 5.5V18.5C26 21.26 23.76 23.5 21 23.5H7C4.24 23.5 2 21.26 2 18.5V5.5Z"
                                                />
                                            </svg>
                                            <span class="sr-only">{{
                                                cell.seatNumber
                                            }}</span>
                                        </button>

                                        <span
                                            aria-hidden="true"
                                            class="w-5 text-left font-display text-[11px] font-semibold text-white/30 select-none"
                                        >
                                            {{ row.rowLetter }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Armchair Legend (Responsive flex-wrap for mobile) -->
                            <div
                                :class="[
                                    'mt-8 flex w-full flex-wrap items-center justify-center gap-x-4 gap-y-2 border-t border-white/10 pt-6 text-xs font-semibold tracking-wider uppercase transition-opacity duration-500 sm:gap-x-7',
                                    hasPickedTime
                                        ? 'opacity-100'
                                        : 'opacity-45',
                                ]"
                            >
                                <div class="inline-flex items-center gap-2">
                                    <svg
                                        viewBox="0 0 28 26"
                                        fill="currentColor"
                                        class="size-5 text-white sm:size-6"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="6"
                                            y="2"
                                            width="16"
                                            height="15"
                                            rx="2.5"
                                        />
                                        <path
                                            d="M2 5.5C2 4.12 3.12 3 4.5 3C5.33 3 6 3.67 6 4.5V16.5C6 17.6 6.9 18.5 8 18.5H20C21.1 18.5 22 17.6 22 16.5V4.5C22 3.67 22.67 3 23.5 3C24.88 3 26 4.12 26 5.5V18.5C26 21.26 23.76 23.5 21 23.5H7C4.24 23.5 2 21.26 2 18.5V5.5Z"
                                        />
                                    </svg>
                                    <span class="text-white/90 tabular-nums">
                                        REGULER -
                                        {{ money(activeSchedule.price) }}
                                    </span>
                                </div>

                                <div class="inline-flex items-center gap-2">
                                    <svg
                                        viewBox="0 0 28 26"
                                        fill="currentColor"
                                        class="size-5 text-[#f5b82e] sm:size-6"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="6"
                                            y="2"
                                            width="16"
                                            height="15"
                                            rx="2.5"
                                        />
                                        <path
                                            d="M2 5.5C2 4.12 3.12 3 4.5 3C5.33 3 6 3.67 6 4.5V16.5C6 17.6 6.9 18.5 8 18.5H20C21.1 18.5 22 17.6 22 16.5V4.5C22 3.67 22.67 3 23.5 3C24.88 3 26 4.12 26 5.5V18.5C26 21.26 23.76 23.5 21 23.5H7C4.24 23.5 2 21.26 2 18.5V5.5Z"
                                        />
                                    </svg>
                                    <span class="text-[#f5b82e]">DIPILIH</span>
                                </div>

                                <div class="inline-flex items-center gap-2">
                                    <svg
                                        viewBox="0 0 28 26"
                                        fill="currentColor"
                                        class="size-5 text-[#2f3138] sm:size-6"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="6"
                                            y="2"
                                            width="16"
                                            height="15"
                                            rx="2.5"
                                        />
                                        <path
                                            d="M2 5.5C2 4.12 3.12 3 4.5 3C5.33 3 6 3.67 6 4.5V16.5C6 17.6 6.9 18.5 8 18.5H20C21.1 18.5 22 17.6 22 16.5V4.5C22 3.67 22.67 3 23.5 3C24.88 3 26 4.12 26 5.5V18.5C26 21.26 23.76 23.5 21 23.5H7C4.24 23.5 2 21.26 2 18.5V5.5Z"
                                        />
                                    </svg>
                                    <span class="text-[#9ca0aa]">TERISI</span>
                                </div>
                            </div>
                        </section>
                    </div>
                </main>
            </div>
        </template>

        <!-- Inline Trailer Video Overlay Modal -->
        <div
            v-if="trailerModalOpen && resolvedTrailerEmbedUrl && activeMovie"
            role="dialog"
            aria-modal="true"
            :aria-label="`Trailer ${activeMovie.title}`"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-4 backdrop-blur-md"
            @click.self="trailerModalOpen = false"
        >
            <div
                class="relative w-full max-w-4xl overflow-hidden rounded-xl border border-white/15 bg-[#121212] shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-white/10 px-4 py-3"
                >
                    <p class="font-display text-sm font-semibold text-white">
                        {{ activeMovie.title }} — Official Trailer
                    </p>
                    <button
                        type="button"
                        aria-label="Tutup trailer"
                        class="rounded-full p-1.5 text-white/70 transition hover:bg-white/10 hover:text-white"
                        @click="trailerModalOpen = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>
                <div class="aspect-video w-full bg-black">
                    <iframe
                        :src="resolvedTrailerEmbedUrl"
                        :title="`Trailer ${activeMovie.title}`"
                        class="h-full w-full"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    />
                </div>
            </div>
        </div>
    </div>
</template>
