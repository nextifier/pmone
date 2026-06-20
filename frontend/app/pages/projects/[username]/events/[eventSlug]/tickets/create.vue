<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack :destination="`${ticketsBase}`" force-destination />
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:ticket-01" class="size-5 sm:size-6" />
        <h1 class="page-title">New Ticket</h1>
      </div>
    </div>

    <TicketForm
      :event="event"
      :saving="saving"
      :errors="errors"
      submit-label="Create Ticket"
      @submit="handleSubmit"
      @cancel="navigateTo(`${ticketsBase}`)"
    />
  </div>
</template>

<script setup>
import TicketForm from "@/components/ticket/TicketForm.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["tickets.create"],
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
  title: computed(() => `New Ticket · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();
const saving = ref(false);
const errors = ref({});

const handleSubmit = async (payload) => {
  saving.value = true;
  errors.value = {};
  try {
    const response = await client(`/api/events/${props.event.id}/tickets`, {
      method: "POST",
      body: payload,
    });
    toast.success("Ticket created");
    await navigateTo(`${ticketsBase.value}/${response.data.slug}`);
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Failed to create ticket", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
};
</script>
