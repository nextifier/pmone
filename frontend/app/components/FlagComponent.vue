<template>
  <span class="flex aspect-3/2 h-4 overflow-hidden rounded-sm">
    <img
      v-if="country"
      :src="flagUrl"
      :alt="countryName"
      :title="countryName"
      loading="lazy"
      decoding="async"
      @error="onImageError"
      class="h-full w-full object-cover"
    />
  </span>
</template>

<script setup>
import { computed, defineProps, ref } from "vue";

const props = defineProps({
  country: {
    required: true,
  },
  countryName: {
    type: String,
    required: false,
  },
});

const imageError = ref(false);

const flagUrl = computed(() => {
  if (imageError.value) {
    return "";
  }
  return `https://flagcdn.com/w40/${props.country.toLowerCase()}.png`;
});

const onImageError = () => {
  imageError.value = true;
};
</script>
