<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()
</script>

<template>
  <h4 data-slot="popover-title" :class="cn('cn-popover-title', props.class)">
    <slot />
  </h4>
</template>
