<template>
  <span class="bg-foreground/20 flex aspect-3/2 h-4 overflow-hidden rounded-sm">
    <img
      v-if="country"
      :src="flagUrl"
      :alt="countryName"
      :title="countryName"
      class="size-full object-cover"
    />
  </span>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
  country: {
    required: true,
  },
  countryName: {
    type: String,
    required: false,
  },
});

const flagUrl = computed(() => {
  return `/flags/${props.country.toLowerCase()}.png`;
});
</script>
