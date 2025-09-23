<template>
  <ComingSoon title="Posts" iconName="hugeicons:task-edit-01" />
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("posts");
</script>
