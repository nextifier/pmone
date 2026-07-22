<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

interface SkeletonProps {
  class?: HTMLAttributes["class"]
}

const props = defineProps<SkeletonProps>()
</script>

<template>
  <!-- Shimmer, not pulse. `animate-skeleton` only moves `background-position`, so it
       needs the moving gradient to be visible — `cn-skeleton`'s flat `bg-muted` would
       animate to nothing on its own. Mirrors the `@utility skeleton` in each app's
       main.css, minus its `rounded-xl` so the per-style `cn-skeleton` radius wins.
       Consumer apps must define `--animate-skeleton` and `@keyframes skeleton`. -->
  <div
    data-slot="skeleton"
    :class="
      cn(
        'cn-skeleton animate-skeleton [--skeleton-highlight:--alpha(var(--color-white)/64%)] [background:linear-gradient(120deg,transparent_40%,var(--skeleton-highlight),transparent_60%)_var(--color-muted)_0_0/200%_100%_fixed] dark:[--skeleton-highlight:--alpha(var(--color-white)/4%)]',
        props.class
      )
    "
  />
</template>
