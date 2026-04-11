<template>
  <ul
    v-if="errors?.length"
    class="text-destructive-foreground flex flex-col gap-y-1 text-sm tracking-tight"
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
