<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-6 pt-4">
    <div class="flex flex-col items-start">
      <BackButton destination="/events" />
      <h2 class="page-title mt-4">Create Event</h2>
      <p class="page-description mt-1.5">Create a new event edition.</p>
    </div>

    <!-- Project Selector -->
    <div class="space-y-2">
      <Label for="project">Project</Label>
      <Select v-model="selectedProjectUsername">
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select a project" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="project in projects" :key="project.id" :value="project.username">
            {{ project.name }}
          </SelectItem>
        </SelectContent>
      </Select>
      <p class="text-muted-foreground text-xs">Select which project this event belongs to.</p>
    </div>

    <FormEvent
      v-if="selectedProjectUsername"
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
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["events.create"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create Event",
});

const router = useRouter();
const { signalRefresh } = useDataRefresh();

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});
const selectedProjectUsername = ref(null);

// Fetch projects
const projects = ref([]);
onMounted(async () => {
  try {
    const response = await client("/api/projects?client_only=true");
    projects.value = response.data || [];

    // Auto-select if only one project
    if (projects.value.length === 1) {
      selectedProjectUsername.value = projects.value[0].username;
    }
  } catch (e) {
    toast.error("Failed to load projects");
  }
});

async function handleCreate(payload) {
  if (!selectedProjectUsername.value) {
    toast.error("Please select a project first");
    return;
  }

  loading.value = true;
  errors.value = {};

  try {
    const response = await client(`/api/projects/${selectedProjectUsername.value}/events`, {
      method: "POST",
      body: payload,
    });

    toast.success("Event created successfully");
    signalRefresh("events-all-list");

    const eventSlug = response?.data?.slug;
    if (eventSlug) {
      router.push(`/projects/${selectedProjectUsername.value}/events/${eventSlug}`);
    } else {
      router.push("/events");
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
</script>
