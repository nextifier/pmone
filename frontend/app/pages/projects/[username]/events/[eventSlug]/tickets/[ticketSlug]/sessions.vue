<template>
  <div class="space-y-4">
    <Alert
      v-if="ticket && ticket.kind === 'entry'"
      class="bg-info/10 border-info/20 text-info-foreground"
    >
      <Icon name="hugeicons:information-circle" />
      <AlertTitle class="tracking-tight">Sessions are for add-on tickets</AlertTitle>
      <AlertDescription class="text-info-foreground/90 tracking-tight">
        This is an entry ticket, so it has no sessions. Change the ticket kind to Add-on in the
        Details tab if it should host sessions.
      </AlertDescription>
    </Alert>

    <SessionsPanel v-else-if="ticket" :event="event" :ticket="ticket" />
  </div>
</template>

<script setup>
import SessionsPanel from "@/components/ticket/SessionsPanel.vue";
import { Alert, AlertTitle, AlertDescription } from "@/components/ui/alert";

defineProps({
  event: Object,
  project: Object,
  ticket: Object,
});

usePageMeta(null, { title: "Sessions · Ticket" });
</script>
