<template>
  <div class="flex flex-col gap-y-8">
    <div class="space-y-1">
      <h3 class="text-lg font-semibold tracking-tight">Event Settings</h3>
      <p class="text-muted-foreground text-sm tracking-tight">
        Manage event status and configuration.
      </p>
    </div>

    <div v-if="event" class="flex flex-col gap-y-8">
      <!-- Event Metadata -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Event Metadata</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2">
            <div>
              <p class="text-muted-foreground text-xs">ULID</p>
              <p class="font-mono text-sm">{{ event.ulid }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs">Slug</p>
              <p class="text-sm font-medium">{{ event.slug }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs">Created</p>
              <p class="text-sm">
                {{ event.created_at ? new Date(event.created_at).toLocaleString() : "-" }}
              </p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs">Last Updated</p>
              <p class="text-sm">
                {{ event.updated_at ? new Date(event.updated_at).toLocaleString() : "-" }}
              </p>
            </div>
            <div v-if="event.creator">
              <p class="text-muted-foreground text-xs">Created By</p>
              <p class="text-sm">{{ event.creator.name }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Management -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Status & Visibility</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-2">
              <Label for="settings-status">Status</Label>
              <Select v-model="statusForm.status">
                <SelectTrigger class="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="draft">Draft</SelectItem>
                  <SelectItem value="published">Published</SelectItem>
                  <SelectItem value="archived">Archived</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="space-y-2">
              <Label for="settings-visibility">Visibility</Label>
              <Select v-model="statusForm.visibility">
                <SelectTrigger class="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="public">Public</SelectItem>
                  <SelectItem value="private">Private</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>

          <div class="mt-4 flex justify-end">
            <button
              type="button"
              :disabled="statusLoading"
              @click="handleStatusUpdate"
              class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight transition disabled:opacity-50"
            >
              <Spinner v-if="statusLoading" />
              {{ statusLoading ? "Saving.." : "Save Changes" }}
            </button>
          </div>
        </div>
      </div>

      <!-- Danger Zone -->
      <div class="frame border-destructive/30">
        <div class="frame-header">
          <div class="frame-title text-destructive">Danger Zone</div>
        </div>
        <div class="frame-panel">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium tracking-tight">Delete Event</p>
              <p class="text-muted-foreground text-xs tracking-tight">
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
  </div>
</template>

<script setup>
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();
const router = useRouter();

const client = useSanctumClient();
const statusLoading = ref(false);
const deleteLoading = ref(false);

const statusForm = reactive({
  status: props.event?.status || "draft",
  visibility: props.event?.visibility || "private",
});

watch(
  () => props.event,
  (newEvent) => {
    if (newEvent) {
      statusForm.status = newEvent.status;
      statusForm.visibility = newEvent.visibility;
    }
  },
  { immediate: true }
);

async function handleStatusUpdate() {
  statusLoading.value = true;

  try {
    await client(`/api/projects/${route.params.username}/events/${route.params.eventSlug}`, {
      method: "PUT",
      body: {
        status: statusForm.status,
        visibility: statusForm.visibility,
      },
    });

    toast.success("Event settings updated");
    await refreshNuxtData(`event-${route.params.username}-${route.params.eventSlug}`);
  } catch (error) {
    toast.error(error.response?._data?.message || "Failed to update settings");
  } finally {
    statusLoading.value = false;
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
  title: computed(() => `Settings - ${props.event?.title || "Event"}`),
});
</script>
