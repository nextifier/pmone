<script setup lang="ts">
import { cn } from "@/lib/utils";
import { computed, onMounted, ref, type HTMLAttributes } from "vue";
import { canUseFullscreen, useLightbox } from "./useLightbox";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();

const state = useLightbox();
const supported = ref(false);

onMounted(() => {
  supported.value = canUseFullscreen();
});

const iconName = computed(() =>
  state.isFullscreen.value ? "lucide:minimize-2" : "lucide:maximize-2",
);
</script>

<template>
  <button
    v-if="supported"
    type="button"
    :aria-label="state.isFullscreen.value ? 'Exit fullscreen' : 'Enter fullscreen'"
    :aria-pressed="state.isFullscreen.value"
    :class="
      cn(
        'group hover:bg-white/15 focus-visible:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 flex size-10 items-center justify-center rounded-full text-white transition-colors focus:outline-hidden disabled:pointer-events-none',
        props.class,
      )
    "
    @click="state.toggleFullscreen"
  >
    <slot :is-fullscreen="state.isFullscreen.value">
      <Icon
        :name="iconName"
        class="size-5 opacity-80 transition-opacity group-hover:opacity-100"
      />
    </slot>
    <span class="sr-only">{{
      state.isFullscreen.value ? "Exit fullscreen" : "Enter fullscreen"
    }}</span>
  </button>
</template>
