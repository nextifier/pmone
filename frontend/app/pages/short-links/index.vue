<template>
  <div class="mx-auto max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-x-2.5 gap-y-4">
      <div class="flex shrink-0 items-center gap-x-2.5">
        <Icon name="hugeicons:user-group" class="size-5 sm:size-6" />
        <h1 class="page-title">Short Links</h1>
      </div>
    </div>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: ["sanctum:auth", "staff-admin-master"],
  layout: "app",
});

defineOptions({
  name: "short-link",
});
const title = "Short Links";
const description = "Shorten your URLs.";

usePageMeta("", {
  title: title,
  description: description,
});
</script>
