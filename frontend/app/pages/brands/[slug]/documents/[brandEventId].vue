<script setup>
// Retired. Operational documents are handled in one place now - the dashboard's
// Operational Documents section - which is the copy that carries i18n, the
// per-document accordion, and the deadline rules. This route stays only so that
// links people have already bookmarked or emailed still land somewhere useful.
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

await navigateTo("/dashboard", { redirectCode: 301 });
</script>

<template>
  <div />
</template>
