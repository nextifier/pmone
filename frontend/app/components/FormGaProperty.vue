<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-8">
    <!-- Basic Information -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Basic Information</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="project_id">Project</Label>
            <Select v-model="formData.project_id" :disabled="loadingProjects">
              <SelectTrigger class="w-full">
                <SelectValue placeholder="Select a project" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem v-for="project in projects" :key="project.id" :value="project.id">
                  {{ project.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputErrorMessage :errors="errors.project_id" />
            <p class="text-muted-foreground text-xs">
              Select the project this GA property belongs to
            </p>
          </div>

          <div class="space-y-2">
            <Label for="name">Property Name</Label>
            <Input
              id="name"
              v-model="formData.name"
              type="text"
              required
              placeholder="Main Website"
            />
            <InputErrorMessage :errors="errors.name" />
            <p class="text-muted-foreground text-xs">Friendly name for this GA4 property</p>
          </div>

          <div class="space-y-2">
            <Label for="property_id">GA4 Property ID</Label>
            <Input
              id="property_id"
              v-model="formData.property_id"
              type="text"
              required
              placeholder="123456789"
            />
            <InputErrorMessage :errors="errors.property_id" />
            <p class="text-muted-foreground text-xs">
              The Google Analytics 4 property ID (numbers only)
            </p>
          </div>

          <div class="space-y-2">
            <Label for="tags">Tags</Label>
            <TagsInput v-model="formData.tags">
              <TagsInputItem v-for="item in formData.tags" :key="item" :value="item">
                <TagsInputItemText />
                <TagsInputItemDelete />
              </TagsInputItem>
              <TagsInputInput placeholder="Add tags..." />
            </TagsInput>
            <InputErrorMessage :errors="errors.tags" />
            <p class="text-muted-foreground text-xs">Organize properties with tags</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Sync Settings -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Sync Settings</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-6">
          <div class="space-y-2">
            <Label for="sync_frequency">Sync Frequency (minutes)</Label>
            <Input
              id="sync_frequency"
              v-model.number="formData.sync_frequency"
              type="number"
              min="5"
              max="60"
              placeholder="10"
            />
            <InputErrorMessage :errors="errors.sync_frequency" />
            <p class="text-muted-foreground text-xs">How often to sync data (5-60 minutes)</p>
          </div>

          <div class="flex items-center gap-2">
            <Switch id="is_active" v-model="formData.is_active" />
            <Label for="is_active">Active</Label>
            <p class="text-muted-foreground ml-auto text-xs">
              Enable automatic data syncing for this property
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Property Information (Edit Mode Only) -->
    <div v-if="mode === 'edit' && gaProperty" class="frame">
      <div class="frame-header">
        <div class="frame-title">Property Information</div>
      </div>
      <div class="frame-panel">
        <div class="text-muted-foreground space-y-3 text-sm">
          <div class="flex items-start justify-between gap-4">
            <span class="font-medium">Sync Status:</span>
            <span class="text-right">
              <template v-if="gaProperty.last_synced_at">
                Last updated {{ $dayjs(gaProperty.last_synced_at).fromNow() }}
                <template v-if="gaProperty.next_sync_at">
                  <br />
                  <span class="text-xs">
                    Next update
                    {{ $dayjs(gaProperty.next_sync_at).fromNow() }}
                  </span>
                </template>
              </template>
              <template v-else>
                <span class="text-warning">Never synced</span>
              </template>
            </span>
          </div>
          <div class="flex items-start justify-between gap-4">
            <span class="font-medium">Created:</span>
            <span>{{ $dayjs(gaProperty.created_at).format("MMM D, YYYY") }}</span>
          </div>
          <div v-if="gaProperty.updated_at" class="flex items-start justify-between gap-4">
            <span class="font-medium">Last Modified:</span>
            <span>{{ $dayjs(gaProperty.updated_at).format("MMM D, YYYY") }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Actions -->
    <div class="flex gap-2">
      <Button type="submit" :disabled="loading">
        <Spinner v-if="loading" class="size-4" />
        <span>{{ loading ? loadingText : submitText }}</span>
      </Button>
      <Button type="button" variant="outline" as-child>
        <nuxt-link to="/ga-properties">Cancel</nuxt-link>
      </Button>
    </div>
  </form>
</template>

<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { toast } from "vue-sonner";

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit"].includes(value),
  },
  gaProperty: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["submit", "update:loading"]);

const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

// Projects list
const projects = ref([]);
const loadingProjects = ref(false);

// Form state
const formData = ref({
  project_id: null,
  name: "",
  property_id: "",
  tags: [],
  is_active: true,
  sync_frequency: 10,
});

const errors = ref({});
const internalLoading = ref(false);

// Computed texts based on mode
const submitText = computed(() => (props.mode === "create" ? "Create Property" : "Save Changes"));
const loadingText = computed(() => (props.mode === "create" ? "Creating..." : "Saving..."));
const loading = computed(() => props.loading || internalLoading.value);

// Fetch projects
async function fetchProjects() {
  loadingProjects.value = true;
  try {
    const response = await sanctumFetch("/api/projects");
    projects.value = response.data || [];
  } catch (error) {
    toast.error("Failed to load projects", {
      description: error?.data?.message || "An error occurred",
    });
  } finally {
    loadingProjects.value = false;
  }
}

// Load projects on mount
onMounted(() => {
  fetchProjects();
});

// Populate form when editing
watch(
  () => props.gaProperty,
  (newProperty) => {
    if (newProperty && props.mode === "edit") {
      formData.value = {
        project_id: newProperty.project_id,
        name: newProperty.name,
        property_id: newProperty.property_id,
        tags: newProperty.tags || [],
        is_active: newProperty.is_active,
        sync_frequency: newProperty.sync_frequency || 10,
      };
    }
  },
  { immediate: true }
);

// Handle submit
async function handleSubmit() {
  internalLoading.value = true;
  errors.value = {};

  try {
    const endpoint =
      props.mode === "create"
        ? "/api/google-analytics/ga-properties"
        : `/api/google-analytics/ga-properties/${props.gaProperty.id}`;

    const method = props.mode === "create" ? "POST" : "PUT";

    // Prepare form data with images
    const submitData = new FormData();

    // Add basic fields
    submitData.append("project_id", formData.value.project_id);
    submitData.append("name", formData.value.name);
    submitData.append("property_id", formData.value.property_id);
    submitData.append("is_active", formData.value.is_active ? "1" : "0");
    submitData.append("sync_frequency", formData.value.sync_frequency);

    // Add tags as array
    if (formData.value.tags && formData.value.tags.length > 0) {
      formData.value.tags.forEach((tag) => {
        submitData.append("tags[]", tag);
      });
    }

    const response = await sanctumFetch(endpoint, {
      method,
      body: submitData,
    });

    toast.success(
      props.mode === "create"
        ? "GA property created successfully"
        : "GA property updated successfully"
    );

    // Redirect to index page
    await navigateTo("/ga-properties");
  } catch (error) {
    if (error.status === 422 && error.data?.errors) {
      errors.value = error.data.errors;
      toast.error("Validation error", {
        description: "Please check the form for errors",
      });
    } else {
      toast.error(
        props.mode === "create" ? "Failed to create GA property" : "Failed to update GA property",
        {
          description: error?.data?.message || error?.message || "An error occurred",
        }
      );
    }
  } finally {
    internalLoading.value = false;
  }
}

// Expose handleSubmit for parent component
defineExpose({ handleSubmit });

defineShortcuts({
  meta_s: {
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
