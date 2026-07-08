<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl xl:max-w-5xl">
    <ButtonBack :destination="ticketsBase" force-destination />

    <div class="space-y-2">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:license" class="size-5 sm:size-6" />
        <h1 class="page-title">Registration Fields</h1>
      </div>
      <p class="page-description">
        Questions every attendee answers - the buyer at checkout, other attendees via their ticket
        links.
      </p>
    </div>

    <CustomFieldsPanel :event="event" context="ticket_registration" library />
  </div>
</template>

<script setup>
import CustomFieldsPanel from "@/components/ticket/CustomFieldsPanel.vue";
import { computed } from "vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["tickets.read"],
  layout: "app",
});

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const ticketsBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/tickets`
);

usePageMeta(null, {
  title: computed(() => `Registration Fields · ${props.event?.title || "Event"}`),
});
</script>
