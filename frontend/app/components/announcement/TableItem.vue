<template>
  <NuxtLink
    :to="`/announcements/${announcement.id}/edit`"
    class="line-clamp-2 text-sm font-medium tracking-tight hover:underline"
  >
    {{ announcement.title }}
  </NuxtLink>
</template>

<script setup>
defineProps({
  announcement: { type: Object, required: true },
});
</script>
