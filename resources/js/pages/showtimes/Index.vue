<script setup lang="ts">
import AstralCinemaStage, {
    type MovieShowcase,
    type Showtime,
} from '@/components/AstralCinemaStage.vue';

withDefaults(
    defineProps<{
        schedules: Showtime[];
        movieShowcases?: Record<string, MovieShowcase>;
        occupiedSeatsBySchedule?: Record<string, number[]>;
        maxSeats?: number;
        holdMinutes?: number;
    }>(),
    {
        movieShowcases: () => ({}),
        occupiedSeatsBySchedule: () => ({}),
        maxSeats: 6,
        holdMinutes: 10,
    },
);
</script>

<template>
    <AstralCinemaStage
        initial-mode="schedule"
        :schedules="schedules"
        :movie-showcases="movieShowcases"
        :occupied-seats-by-schedule="occupiedSeatsBySchedule"
        :max-seats="maxSeats"
        :hold-minutes="holdMinutes"
        :can-return-to-schedule="true"
    />
</template>
