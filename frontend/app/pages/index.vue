<template>
  <div class="space-y-10 pb-16 lg:space-y-16">
    <Hero />
    <LazyBlogPostSlider />
  </div>
</template>

<script setup>
definePageMeta({
  //   middleware: ["sanctum:guest"],
});
usePageMeta("home");
</script>
