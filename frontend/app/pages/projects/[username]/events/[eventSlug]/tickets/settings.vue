<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl xl:max-w-5xl">
    <ButtonBack :destination="ticketsBase" force-destination />
    <div class="space-y-2">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:settings-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Ticket Settings</h1>
      </div>
      <p class="page-description">
        Enable ticketing for this event and set the defaults applied to new tickets. Changes are
        saved automatically.
      </p>
    </div>

    <TicketSettingsForm :event="event" />

    <EventDaysToggle v-if="event?.id" :event="event" />
  </div>
</template>

<script setup>
import EventDaysToggle from "@/components/ticket/EventDaysToggle.vue";
import TicketSettingsForm from "@/components/ticket/TicketSettingsForm.vue";

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
  title: computed(() => `Ticket Settings · ${props.event?.title || "Event"}`),
});
</script>
