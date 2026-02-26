<template>
  <ComingSoon title="Settings" iconName="hugeicons:settings-01" />
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, { title: "Settings" });
</script>
