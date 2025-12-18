<template>
  <div class="space-y-10">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Permission Name</label>
        <input
          id="name"
          v-model="formData.name"
          type="text"
          required
          placeholder="posts.publish"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.name }"
        />
        <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name[0] }}</p>
        <p class="text-muted-foreground text-xs">
          Use dot notation (e.g., "resource.action"). Will be automatically converted to slug format.
        </p>
      </div>

      <div class="flex gap-2">
        <button
          type="submit"
          :disabled="loading"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-2 rounded-md px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Spinner v-if="loading" class="size-4" />
          <span>{{ loading ? loadingText : submitText }}</span>
        </button>
        <nuxt-link
          to="/permissions"
          class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          Cancel
        </nuxt-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit"].includes(value),
  },
  permission: {
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

// Form state
const formData = ref({
  name: "",
});

const errors = ref({});
const internalLoading = ref(false);

// Computed texts based on mode
const submitText = computed(() => (props.mode === "create" ? "Create Permission" : "Save Changes"));
const loadingText = computed(() => (props.mode === "create" ? "Creating..." : "Saving..."));
const loading = computed(() => props.loading || internalLoading.value);

// Populate form when editing
watch(
  () => props.permission,
  (newPermission) => {
    if (newPermission && props.mode === "edit") {
      formData.value = {
        name: newPermission.name,
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
    const endpoint = props.mode === "create" ? "/api/permissions" : `/api/permissions/${props.permission.id}`;

    const method = props.mode === "create" ? "POST" : "PUT";

    const response = await sanctumFetch(endpoint, {
      method,
      body: formData.value,
    });

    if (response.data) {
      const successMessage =
        props.mode === "create" ? "Permission created successfully!" : "Permission updated successfully!";
      toast.success(successMessage);
      navigateTo("/permissions");
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        err.response?._data?.message || err.message || `Failed to ${props.mode} permission`;
      toast.error(errorMessage);
    }
    console.error(`Error ${props.mode}ing permission:`, err);
  } finally {
    internalLoading.value = false;
  }
}

// Expose submit handler for keyboard shortcuts
defineExpose({
  handleSubmit,
});
</script>
