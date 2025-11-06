<template>
  <ComingSoon title="Web Analytics" iconName="hugeicons:analysis-text-link" />
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("", {
  title: `Web Analytics`,
  description: "",
});
</script>
