<template>
  <ComingSoon title="Reports" iconName="hugeicons:analysis-text-link" />
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("reports");
</script>
