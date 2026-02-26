<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-6">
    <div class="flex flex-col items-start">
      <BackButton :destination="`/projects/${route.params.username}/events`" />
      <h2 class="page-title mt-4">Create Event</h2>
      <p class="page-description mt-1.5">Create a new event edition for this project.</p>
    </div>

    <FormEvent
      :is-create="true"
      :loading="loading"
      :errors="errors"
      submit-text="Create Event"
      submit-loading-text="Creating.."
      @submit="handleCreate"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

const route = useRoute();
const router = useRouter();

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});

async function handleCreate(payload) {
  loading.value = true;
  errors.value = {};

  try {
    const response = await client(`/api/projects/${route.params.username}/events`, {
      method: "POST",
      body: payload,
    });

    toast.success("Event created successfully");

    const eventSlug = response?.data?.slug;
    if (eventSlug) {
      router.push(`/projects/${route.params.username}/events/${eventSlug}`);
    } else {
      router.push(`/projects/${route.params.username}/events`);
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to create event");
    }
  } finally {
    loading.value = false;
  }
}

usePageMeta(null, {
  title: "Create Event",
});
</script>
