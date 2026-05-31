<script setup lang="ts">
import { cn } from "@/lib/utils";
import { NuxtLink } from "#components";
import { Primitive } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<{
    to?: string;
    icon?: string;
    label?: string;
    as?: string;
    class?: HTMLAttributes["class"];
  }>(),
  {
    icon: "hugeicons:add-01",
    as: "button",
  },
);

const emit = defineEmits<{ select: [] }>();

const isExternal = computed(() => props.to?.startsWith("http"));

function handleSelect(): void {
  emit("select");
}
</script>

<template>
  <component
    :is="to ? NuxtLink : Primitive"
    :to="to || undefined"
    :as="to ? undefined : as"
    :type="!to && as === 'button' ? 'button' : undefined"
    :target="isExternal ? '_blank' : undefined"
    :rel="isExternal ? 'noopener noreferrer' : undefined"
    :aria-label="label || undefined"
    data-slot="bottom-nav-action"
    :class="
      cn(
        'group relative z-10 flex flex-1 select-none flex-col items-center justify-center gap-y-0.5 px-2 outline-none',
        props.class,
      )
    "
    @click="handleSelect"
  >
    <span
      :class="
        cn(
          'bg-primary text-primary-foreground flex size-12 -translate-y-3 items-center justify-center rounded-full shadow-lg transition-[transform,background-color] duration-200 ease-out',
          'hover:bg-primary/90 active:scale-95 motion-reduce:transition-none',
          'ring-4 ring-background group-focus-visible:ring-2 group-focus-visible:ring-ring group-focus-visible:ring-offset-2 group-focus-visible:ring-offset-background',
        )
      "
    >
      <Icon :name="icon" class="size-6" aria-hidden="true" />
    </span>
    <span
      v-if="label"
      class="text-muted-foreground -mt-2 text-xs font-medium tracking-tight sm:text-sm"
    >
      {{ label }}
    </span>
  </component>
</template>
