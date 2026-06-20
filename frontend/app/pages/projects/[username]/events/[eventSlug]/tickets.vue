<template>
  <div class="flex flex-col gap-y-0">
    <NuxtPage :event="event" :project="project" />
  </div>
</template>

<script setup>
// Thin wrapper: the Tickets section no longer has its own sub-TabNav. Attendees
// and Business Matching are now top-level event tabs; Event Days and Settings are
// reached via buttons on the ticket list page. This wrapper only forwards the
// event/project props down to the ticket pages (index / settings / days /
// create / [ticketSlug]).
defineProps({
  event: Object,
  project: Object,
});
</script>
