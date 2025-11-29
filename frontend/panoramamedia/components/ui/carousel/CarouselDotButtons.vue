<script setup lang="ts">
import { useCarousel } from "./useCarousel";
import type { WithClassAsProps } from "./interface";
import { cn } from "@/lib/utils";

const props = defineProps<
  WithClassAsProps & {
    activeClass?: string;
    inactiveClass?: string;
  }
>();

const { scrollTo, selectedIndex, scrollSnaps } = useCarousel();
</script>

<template>
  <button
    type="button"
    v-for="(_, index) in scrollSnaps"
    :key="index"
    :class="[
      cn(props.class),
      index === selectedIndex ? props.activeClass : props.inactiveClass,
    ]"
    @click="scrollTo(index)"
  />
</template>
