<template>
  <button
    @click="copy(text)"
    :disabled="copied"
    :aria-label="copied ? 'Copied' : 'Copy to clipboard'"
    v-tippy="copied ? 'Copied' : 'Copy to clipboard'"
    class="text-muted-foreground hover:text-foreground flex size-7 items-center justify-center rounded-lg disabled:opacity-100"
  >
    <div :class="['transition-all', copied ? 'scale-100 opacity-100' : 'scale-0 opacity-0']">
      <Icon
        name="lucide:check"
        class="text-success-foreground size-4 shrink-0"
        aria-hidden="true"
      />
    </div>
    <div
      :class="['absolute transition-all', copied ? 'scale-0 opacity-0' : 'scale-100 opacity-100']"
    >
      <Icon name="lucide:copy" class="size-3.5 shrink-0" aria-hidden="true" />
    </div>
  </button>
</template>

<script setup>
import { useClipboard } from "@vueuse/core";
const { copy, copied } = useClipboard();

defineProps({
  text: {
    type: String,
    required: true,
  },
});
</script>
