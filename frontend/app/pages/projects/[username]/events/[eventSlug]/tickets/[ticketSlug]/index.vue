<template>
  <div class="mx-auto w-full max-w-4xl">
    <TicketForm
      v-if="ticket"
      :event="event"
      :initial="ticket"
      :saving="saving"
      :errors="errors"
      submit-label="Save Changes"
      @submit="handleSubmit"
      @cancel="navigateTo(`${ticketsBase}`)"
    />
  </div>
</template>

<script setup>
import TicketForm from "@/components/ticket/TicketForm.vue";
import { toast } from "vue-sonner";

const props = defineProps({
  event: Object,
  project: Object,
  ticket: Object,
});

const emit = defineEmits(["refresh"]);

const route = useRoute();
const ticketSlug = computed(() => route.params.ticketSlug);

const ticketsBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}/tickets`
);

usePageMeta(null, { title: "Details · Ticket" });

const client = useSanctumClient();
const { hasPermission } = usePermission();
const canUpdate = computed(() => hasPermission("tickets.update"));

const saving = ref(false);
const errors = ref({});

const handleSubmit = async (payload) => {
  if (!canUpdate.value) {
    toast.error("You do not have permission to edit tickets");
    return;
  }
  saving.value = true;
  errors.value = {};
  try {
    const response = await client(
      `/api/events/${props.event.id}/tickets/${ticketSlug.value}`,
      {
        method: "PUT",
        body: payload,
      }
    );
    toast.success("Ticket updated");
    // Slug may change when the title changes; navigate to the canonical URL.
    if (response.data?.slug && response.data.slug !== ticketSlug.value) {
      await navigateTo(`${ticketsBase.value}/${response.data.slug}`);
    } else {
      emit("refresh");
    }
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Failed to update ticket", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
};
</script>
