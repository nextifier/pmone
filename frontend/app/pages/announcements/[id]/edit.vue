<template>
  <div class="mx-auto max-w-2xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/announcements" />

      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:notification-02" class="size-5 shrink-0 sm:size-6" />
        <h1 class="page-title">Edit Announcement</h1>
      </div>
      <p v-if="initialData?.title" class="page-description">
        {{ initialData.title }}
      </p>
    </div>

    <div v-if="fetching" class="space-y-4">
      <Skeleton class="h-10 w-full rounded-md" />
      <Skeleton class="h-32 w-full rounded-md" />
      <Skeleton class="h-10 w-full rounded-md" />
    </div>

    <FormAnnouncement
      v-else-if="initialData"
      :initial-data="initialData"
      :loading="loading"
      :errors="errors"
      submit-text="Save Announcement"
      submit-loading-text="Saving.."
      @submit="handleUpdate"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["announcements.update"],
  layout: "app",
});

usePageMeta(null, { title: "Edit Announcement" });

const route = useRoute();
const client = useSanctumClient();
const loading = ref(false);
const fetching = ref(true);
const errors = ref({});
const initialData = ref(null);

async function fetchAnnouncement() {
  try {
    fetching.value = true;
    const response = await client(`/api/announcements/${route.params.id}`);
    initialData.value = response?.data;
  } catch (error) {
    toast.error("Failed to load announcement");
    await navigateTo("/announcements");
  } finally {
    fetching.value = false;
  }
}

async function handleUpdate(payload) {
  loading.value = true;
  errors.value = {};
  try {
    await client(`/api/announcements/${route.params.id}`, { method: "PUT", body: payload });
    toast.success("Announcement updated");
    await navigateTo("/announcements");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to update announcement");
    }
  } finally {
    loading.value = false;
  }
}

onMounted(fetchAnnouncement);
</script>
