<template>
  <ComingSoon title="Inbox" iconName="hugeicons:mail-open-love" />
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("inbox");
</script>
