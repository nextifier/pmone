<template>
  <div
    v-if="prev || next"
    class="mx-auto mt-12 flex max-w-3xl items-center justify-between gap-x-2"
  >
    <Button v-if="prev" :to="`/ui/${prev.name}`" variant="secondary">
      <Icon name="lucide:chevron-left" />
      {{ prev.title }}
    </Button>
    <div v-else />

    <Button v-if="next" :to="`/ui/${next.name}`" variant="secondary" class="ml-auto">
      {{ next.title }}
      <Icon name="lucide:chevron-right" />
    </Button>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";

defineProps({
  prev: { type: Object, default: null },
  next: { type: Object, default: null },
});
</script>
