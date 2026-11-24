<script setup lang="ts">
import { computed } from 'vue';
import AstralCinemaStage, {
    type MovieShowcase,
    type Showtime,
    type StageMode,
} from '@/components/AstralCinemaStage.vue';

const props = withDefaults(
    defineProps<{
        schedule: Showtime;
        movieShowcase?: MovieShowcase;
        siblingSchedules?: Showtime[];
        occupiedSeats: number[];
        maxSeats: number;
        holdMinutes: number;
        initialViewMode?: StageMode;
    }>(),
    {
        movieShowcase: () => ({
            grade: null,
            country: null,
            language: null,
            director: null,
            synopsis: null,
            trailer_url: null,
            trailer_embed_url: null,
            cast: [],
        }),
        siblingSchedules: () => [],
        initialViewMode: 'booking',
    },
);

const combinedSchedules = computed<Showtime[]>(() => {
    const map = new Map<number, Showtime>();
    map.set(props.schedule.id_schedule, props.schedule);
    for (const s of props.siblingSchedules) {
        if (!map.has(s.id_schedule)) {
            map.set(s.id_schedule, {
                ...s,
                movie: s.movie ?? props.schedule.movie,
                cinema: s.cinema ?? props.schedule.cinema,
            });
        }
    }
    return Array.from(map.values());
});

const showcasesMap = computed<Record<string, MovieShowcase>>(() => {
    const idMovie = props.schedule.movie.id_movie;
    if (!idMovie) return {};
    return { [String(idMovie)]: props.movieShowcase };
});

const occupiedMap = computed<Record<string, number[]>>(() => ({
    [String(props.schedule.id_schedule)]: props.occupiedSeats,
}));
</script>

<template>
    <AstralCinemaStage
        :initial-mode="initialViewMode"
        :initial-schedule-id="schedule.id_schedule"
        :initial-time-picked="initialViewMode === 'booking'"
        :schedules="combinedSchedules"
        :movie-showcases="showcasesMap"
        :occupied-seats-by-schedule="occupiedMap"
        :max-seats="maxSeats"
        :hold-minutes="holdMinutes"
        :can-return-to-schedule="false"
    />
</template>
