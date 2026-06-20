<template>
  <button
    type="button"
    :disabled="disabled || loading"
    class="flex shrink-0 items-center gap-x-1.5 rounded-full px-1.5 py-2 text-sm font-medium tracking-tight transition active:scale-98 disabled:opacity-50 sm:px-3"
    :class="
      destructive
        ? 'text-destructive hover:bg-destructive/15'
        : 'text-background hover:bg-background/15'
    "
    @click="$emit('click', $event)"
  >
    <Spinner v-if="loading" class="size-4 shrink-0" />
    <span v-else class="t-icon-swap-slot grid">
      <Transition name="t-iswap" mode="out-in">
        <Icon :key="icon" :name="icon" class="size-4 shrink-0" />
      </Transition>
    </span>
    <Transition name="t-tswap" mode="out-in">
      <span :key="label" class="inline-block whitespace-nowrap">{{ label }}</span>
    </Transition>
    <slot />
  </button>
</template>

<script setup>
import { Spinner } from "@/components/ui/spinner";

defineProps({
  icon: {
    type: String,
    required: true,
  },
  label: {
    type: String,
    required: true,
  },
  destructive: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

defineEmits(["click"]);
</script>
