<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-6">
    <div class="flex flex-col items-start">
      <h2 class="page-title mt-3">Event Details</h2>
      <p class="page-description mt-1.5">Edit event information.</p>
    </div>

    <FormEvent
      :initial-data="event"
      :loading="loading"
      :errors="errors"
      submit-text="Update Event"
      submit-loading-text="Updating.."
      @submit="handleUpdate"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});

async function handleUpdate(payload) {
  loading.value = true;
  errors.value = {};

  try {
    await client(`/api/projects/${route.params.username}/events/${route.params.eventSlug}`, {
      method: "PUT",
      body: payload,
    });

    toast.success("Event updated successfully");

    // Refresh parent event data
    await refreshNuxtData(`event-${route.params.username}-${route.params.eventSlug}`);
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to update event");
    }
  } finally {
    loading.value = false;
  }
}

usePageMeta(null, {
  title: computed(() => `Details - ${props.event?.title || "Event"}`),
});
</script>
