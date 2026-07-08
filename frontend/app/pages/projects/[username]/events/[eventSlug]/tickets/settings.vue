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

    <NuxtLink
      :to="`${ticketsBase}/registration-fields`"
      class="bg-card hover:border-foreground/20 flex items-center gap-x-3 rounded-xl border p-4 transition-colors sm:p-5"
    >
      <div
        class="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-lg"
      >
        <Icon name="hugeicons:license" class="size-4" />
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium tracking-tight">Registration Fields</p>
        <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
          Custom questions every attendee answers at checkout and on their ticket links.
        </p>
      </div>
      <Icon name="hugeicons:arrow-right-01" class="text-muted-foreground size-4 shrink-0" />
    </NuxtLink>
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
