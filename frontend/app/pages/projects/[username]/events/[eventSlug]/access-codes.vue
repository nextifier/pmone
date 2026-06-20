<template>
  <div>
    <NuxtPage :event="event" :project="project" />
  </div>
</template>

<script setup>
// Thin wrapper forwarding event/project down to the access-codes pages.
defineProps({
  event: Object,
  project: Object,
});
</script>
