<template>
  <!--
    No `max-w-*` cap and no bottom padding: the preview needs the full column,
    and space after a panel whose preview is `sticky` gets taken out of the
    pinned preview at the end of the scroll. See `preview-panel/context.ts`.
  -->
  <CustomFieldPreviewPanel
    :fields="previewFields"
    value-key="ulid"
    preview-title="Attendee registration"
    empty-message="Add a field to see the form attendees fill in."
  >
    <div class="space-y-6">
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

      <CustomFieldsPanel
        :event="event"
        context="ticket_registration"
        library
        @update:fields="previewFields = $event"
      />
    </div>
  </CustomFieldPreviewPanel>
</template>

<script setup>
import CustomFieldsPanel from "@/components/ticket/CustomFieldsPanel.vue";
import { computed, ref } from "vue";

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

/** Streamed out of CustomFieldsPanel so the pane beside it can render them. */
const previewFields = ref([]);

usePageMeta(null, {
  title: computed(() => `Registration Fields · ${props.event?.title || "Event"}`),
});
</script>
