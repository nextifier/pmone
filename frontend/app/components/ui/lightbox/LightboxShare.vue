<script setup lang="ts">
import { cn } from "@/lib/utils";
import { onMounted, ref, type HTMLAttributes } from "vue";
import { canUseShare, useLightbox } from "./useLightbox";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();

const state = useLightbox();
const supported = ref(false);

onMounted(() => {
  supported.value = canUseShare();
});
</script>

<template>
  <button
    v-if="supported"
    type="button"
    aria-label="Share"
    :class="
      cn(
        'group hover:bg-white/15 focus-visible:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 flex size-10 items-center justify-center rounded-full text-white transition-colors focus:outline-hidden disabled:pointer-events-none',
        props.class,
      )
    "
    @click="state.share"
  >
    <slot>
      <Icon
        name="lucide:share-2"
        class="size-5 opacity-80 transition-opacity group-hover:opacity-100"
      />
    </slot>
    <span class="sr-only">Share</span>
  </button>
</template>
