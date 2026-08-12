<template>
  <!--
    No `max-w-*` cap and no bottom padding: the preview needs the full column,
    and space after a panel whose preview is `sticky` gets taken out of the
    pinned preview at the end of the scroll. See `preview-panel/context.ts`.
  -->
  <PreviewPanel
    v-model:tab="activeTab"
    ratio="3:2"
    offset="calc(var(--navbar-height-desktop) + 1rem)"
    preview-title="User dashboard"
    client-only-preview
  >
    <template #edit>
      <div class="space-y-6 pt-4">
        <div class="flex flex-col items-start gap-y-4">
          <ButtonBack destination="/announcements" force-destination />

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
          @update:preview="draft = $event"
        />
      </div>
    </template>

    <template #preview>
      <div class="px-4 py-6">
        <Skeleton v-if="fetching" class="h-20 w-full rounded-lg" />
        <AnnouncementPreviewCard v-else :announcement="draft" />
      </div>
    </template>
  </PreviewPanel>
</template>

<script setup>
import { PreviewPanel } from "@/components/ui/preview-panel";
import { Skeleton } from "@/components/ui/skeleton";
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
const activeTab = ref("edit");

/** Live copy of the form, streamed out of FormAnnouncement as it is edited. */
const draft = ref({});

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
