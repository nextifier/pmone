<template>
  <div class="mx-auto max-w-2xl space-y-6 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/announcements" />

      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:notification-02" class="size-5 shrink-0 sm:size-6" />
        <h1 class="page-title">Create Announcement</h1>
      </div>
      <p class="page-description">
        Add a new announcement that will appear on user dashboards.
      </p>
    </div>

    <FormAnnouncement
      :is-create="true"
      :loading="loading"
      :errors="errors"
      submit-text="Create Announcement"
      submit-loading-text="Creating.."
      @submit="handleCreate"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["announcements.create"],
  layout: "app",
});

usePageMeta(null, { title: "Create Announcement" });

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});

async function handleCreate(payload) {
  loading.value = true;
  errors.value = {};
  try {
    await client("/api/announcements", { method: "POST", body: payload });
    toast.success("Announcement created");
    await navigateTo("/announcements");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to create announcement");
    }
  } finally {
    loading.value = false;
  }
}
</script>
