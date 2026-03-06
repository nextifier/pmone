<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-6">
    <div class="flex flex-col items-start">
      <NuxtLink
        :to="base"
        class="text-muted-foreground hover:text-foreground mb-2 flex items-center gap-x-1 text-sm tracking-tight transition"
      >
        <Icon name="hugeicons:arrow-left-01" class="size-4" />
        <span>Back to Overview</span>
      </NuxtLink>
      <h2 class="page-title mt-1">Edit Event Details</h2>
      <p class="page-description mt-1.5">Edit event information, status, and configuration.</p>
    </div>

    <FormEvent
      :initial-data="event"
      :loading="loading"
      :errors="errors"
      submit-text="Update Event"
      submit-loading-text="Updating.."
      @submit="handleUpdate"
    />

    <!-- Event Metadata -->
    <div v-if="event" class="frame">
      <div class="frame-header">
        <div class="frame-title">Event Metadata</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2">
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">ULID</p>
            <p class="font-mono text-sm">{{ event.ulid }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Slug</p>
            <p class="text-sm font-medium">{{ event.slug }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Created</p>
            <p class="text-sm">
              {{ event.created_at ? new Date(event.created_at).toLocaleString() : "-" }}
            </p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Last Updated</p>
            <p class="text-sm">
              {{ event.updated_at ? new Date(event.updated_at).toLocaleString() : "-" }}
            </p>
          </div>
          <div v-if="event.creator">
            <p class="text-muted-foreground text-xs sm:text-sm">Created By</p>
            <p class="text-sm">{{ event.creator.name }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Danger Zone -->
    <div v-if="event" class="frame border-destructive/30">
      <div class="frame-header">
        <div class="frame-title text-destructive">Danger Zone</div>
      </div>
      <div class="frame-panel">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium tracking-tight">Delete Event</p>
            <p class="text-muted-foreground text-xs sm:text-sm tracking-tight">
              Move this event to trash. It can be restored later.
            </p>
          </div>
          <button
            type="button"
            :disabled="deleteLoading"
            @click="handleDelete"
            class="bg-destructive hover:bg-destructive/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight text-white transition disabled:opacity-50"
          >
            <Spinner v-if="deleteLoading" />
            {{ deleteLoading ? "Deleting.." : "Delete Event" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const router = useRouter();

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});
const deleteLoading = ref(false);

const base = computed(() => `/projects/${route.params.username}/events/${route.params.eventSlug}`);

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

async function handleDelete() {
  if (!confirm("Are you sure you want to delete this event?")) return;

  deleteLoading.value = true;

  try {
    await client(`/api/projects/${route.params.username}/events/${route.params.eventSlug}`, {
      method: "DELETE",
    });

    toast.success("Event deleted");
    router.push(`/projects/${route.params.username}/events`);
  } catch (error) {
    toast.error(error.response?._data?.message || "Failed to delete event");
  } finally {
    deleteLoading.value = false;
  }
}

usePageMeta(null, {
  title: computed(() => `Edit Details · ${props.event?.title || "Event"}`),
});
</script>
