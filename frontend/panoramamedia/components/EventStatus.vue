<template>
  <div class="text-primary flex items-center justify-center">
    <LoadingChaoticOrbit v-if="!isMounted" class="border-primary size-4" />

    <template v-else>
      <div v-if="status === 'upcoming'" class="flex items-center gap-x-1.5">
        <span class="inline tracking-tighter">
          <Countdown
            :variant="countdownVariant"
            :textBeforeCountdown="withTextPrefix ? 'The event starts in ' : ''"
            :countdownDate="startTime"
          />
        </span>
      </div>

      <div v-else-if="status === 'live'" class="flex items-center gap-x-1.5">
        <LoaderBars />
        <span class="font-bold tracking-normal uppercase">Live</span>
      </div>

      <div
        v-else-if="status === 'completed'"
        class="flex items-center gap-x-1.5"
      >
        <IconCompleted class="size-5 shrink-0 text-green-500" />
        <span class="text-sm tracking-tight">Completed </span>
      </div>
    </template>
  </div>
</template>

<script setup>
import { useNow } from "@/composables/useNow";

const props = defineProps({
  countdownVariant: {
    type: String,
    default: "no-style",
  },
  startTime: Date,
  endTime: Date,
  withTextPrefix: {
    type: Boolean,
    default: true,
  },
});

const { now } = useNow();
const isMounted = ref(false);

onMounted(() => {
  isMounted.value = true;
});

const status = computed(() => {
  if (!isMounted.value) return null;

  if (!props.startTime || !props.endTime) return null;

  const currentTime = now.value.getTime();

  if (currentTime < props.startTime.getTime()) {
    return "upcoming";
  } else if (
    currentTime > props.startTime.getTime() &&
    currentTime < props.endTime.getTime()
  ) {
    return "live";
  } else {
    return "completed";
  }
});
</script>
