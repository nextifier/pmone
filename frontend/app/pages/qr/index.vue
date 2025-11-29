<template>
  <div
    class="min-h-screen-offset mx-auto flex flex-col space-y-6 pt-4 pb-4 lg:max-w-4xl xl:max-w-6xl"
  >
    <div class="flex flex-col gap-y-1">
      <h2 class="page-title">QR Code Generator</h2>
      <!-- <p class="page-description"></p> -->
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, {
  title: "Generate QR Code",
});
</script>
