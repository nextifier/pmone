<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { onBeforeUnmount, onMounted, useTemplateRef } from "vue";
import { cn } from "@/lib/utils";
import { useMessageScrollerEngine } from "./context";

const props = defineProps<{ class?: HTMLAttributes["class"] }>();

const engine = useMessageScrollerEngine();
const el = useTemplateRef<HTMLElement>("root");

onMounted(() => engine.setRootElement(el.value));
onBeforeUnmount(() => engine.setRootElement(null));
</script>

<template>
  <div
    ref="root"
    data-slot="message-scroller"
    :class="
      cn(
        'group/message-scroller relative flex size-full min-h-0 flex-col overflow-hidden',
        props.class
      )
    "
  >
    <slot />
  </div>
</template>
