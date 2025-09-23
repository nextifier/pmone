<template>
  <ComingSoon title="Projects" iconName="hugeicons:layers-01" />
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("projects");
</script>
