<template>
  <ul
    v-if="errors?.length"
    class="flex flex-col gap-y-1 text-sm tracking-tight text-red-600 dark:text-red-500"
  >
    <li v-for="(error, index) in errors" :key="index">
      {{ error }}
    </li>
  </ul>
</template>

<script setup>
defineProps({
  errors: {
    type: Array,
    default: null,
  },
});
</script>

<style></style>
