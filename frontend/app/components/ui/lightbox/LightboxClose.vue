<script setup lang="ts">
import { cn } from "@/lib/utils";
import { DialogClose } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
}>();
</script>

<template>
  <DialogClose
    data-slot="lightbox-close"
    aria-label="Close"
    :class="
      cn(
        'group hover:bg-white/15 focus-visible:bg-white/15 focus-visible:ring-2 focus-visible:ring-white/40 flex size-10 items-center justify-center rounded-full text-white transition-colors focus:outline-hidden disabled:pointer-events-none',
        props.class,
      )
    "
  >
    <slot>
      <Icon name="lucide:x" class="size-5 opacity-80 transition-opacity group-hover:opacity-100" />
    </slot>
    <span class="sr-only">Close</span>
  </DialogClose>
</template>
