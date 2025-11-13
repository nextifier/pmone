<template>
  <div class="space-y-10 lg:space-y-16">
    <Hero />
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:guest"],
});
usePageMeta("home");
</script>
