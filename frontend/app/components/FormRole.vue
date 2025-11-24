<template>
  <div class="space-y-10">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Role Name</label>
        <input
          id="name"
          v-model="formData.name"
          type="text"
          required
          placeholder="editor"
          class="border-border bg-background focus:ring-primary w-full rounded-md border px-3 py-2 text-sm tracking-tight focus:ring-2 focus:outline-none"
          :class="{ 'border-destructive': errors.name }"
        />
        <p v-if="errors.name" class="text-destructive text-xs">{{ errors.name[0] }}</p>
        <p class="text-muted-foreground text-xs">
          The role name (lowercase, no spaces)
        </p>
      </div>

      <div class="space-y-3">
        <label class="text-sm font-medium">Permissions</label>
        <p class="text-muted-foreground text-xs -mt-2">
          Select which permissions this role should have
        </p>

        <div v-if="loadingPermissions" class="flex items-center gap-2 py-4">
          <Spinner class="size-4" />
          <span class="text-muted-foreground text-sm">Loading permissions...</span>
        </div>

        <div v-else-if="permissionsError" class="border-destructive text-destructive rounded-md border p-3 text-sm">
          Failed to load permissions
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="group in permissionGroups"
            :key="group.group"
            class="border-border rounded-lg border p-4 space-y-3"
          >
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-medium">{{ group.label }}</h3>
                <button
                  type="button"
                  @click="toggleGroupPermissions(group.permissions)"
                  class="text-primary hover:text-primary/80 text-xs font-medium"
                >
                  {{ areAllGroupPermissionsSelected(group.permissions) ? 'Deselect All' : 'Select All' }}
                </button>
              </div>
              <p v-if="group.description" class="text-muted-foreground text-xs">
                {{ group.description }}
              </p>
            </div>

            <div class="space-y-2">
              <div
                v-for="permission in group.permissions"
                :key="permission.name"
                class="flex items-center gap-2"
              >
                <Checkbox
                  :id="`permission-${permission.name}`"
                  :model-value="formData.permissions.includes(permission.name)"
                  @update:model-value="(checked) => togglePermission(permission.name, checked)"
                />
                <label
                  :for="`permission-${permission.name}`"
                  class="text-sm tracking-tight cursor-pointer grow"
                >
                  <span>{{ formatPermissionLabel(permission) }}</span>
                  <span v-if="permission.description && group.type === 'custom'" class="text-muted-foreground text-xs ml-2">
                    ({{ permission.description }})
                  </span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <p v-if="errors.permissions" class="text-destructive text-xs">{{ errors.permissions[0] }}</p>
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
          to="/roles"
          class="border-border hover:bg-muted rounded-md border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
        >
          Cancel
        </nuxt-link>
      </div>
    </form>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { toast } from "vue-sonner";

const props = defineProps({
  mode: {
    type: String,
    required: true,
    validator: (value) => ["create", "edit"].includes(value),
  },
  role: {
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
  permissions: [],
});

const errors = ref({});
const internalLoading = ref(false);

// Permissions state
const permissionGroups = ref([]);
const loadingPermissions = ref(false);
const permissionsError = ref(false);

// Computed texts based on mode
const submitText = computed(() => (props.mode === "create" ? "Create Role" : "Save Changes"));
const loadingText = computed(() => (props.mode === "create" ? "Creating..." : "Saving..."));
const loading = computed(() => props.loading || internalLoading.value);

// Helper functions
function formatPermissionLabel(permission) {
  // For resource-based permissions (CRUD), format the action nicely
  if (permission.action) {
    const actionLabels = {
      create: 'Create',
      read: 'View/Read',
      update: 'Update/Edit',
      delete: 'Delete',
    };
    return actionLabels[permission.action] || permission.action.charAt(0).toUpperCase() + permission.action.slice(1);
  }

  // For custom permissions, use description or format from name
  if (permission.description) {
    return permission.description;
  }

  // Fallback: format from permission name
  const [, action] = permission.name.split('.');
  return action
    ? action.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
    : permission.name;
}

function areAllGroupPermissionsSelected(groupPermissions) {
  return groupPermissions.every(p => formData.value.permissions.includes(p.name));
}

function togglePermission(permissionName, checked) {
  const index = formData.value.permissions.indexOf(permissionName);
  const isChecked = checked === true || checked === "indeterminate";

  if (isChecked) {
    if (index === -1) {
      formData.value.permissions.push(permissionName);
    }
  } else {
    if (index > -1) {
      formData.value.permissions.splice(index, 1);
    }
  }
}

function toggleGroupPermissions(groupPermissions) {
  const allSelected = areAllGroupPermissionsSelected(groupPermissions);

  if (allSelected) {
    // Deselect all
    groupPermissions.forEach(p => {
      const index = formData.value.permissions.indexOf(p.name);
      if (index > -1) {
        formData.value.permissions.splice(index, 1);
      }
    });
  } else {
    // Select all
    groupPermissions.forEach(p => {
      if (!formData.value.permissions.includes(p.name)) {
        formData.value.permissions.push(p.name);
      }
    });
  }
}

// Load permissions (now returns grouped permissions from API)
async function loadPermissions() {
  try {
    loadingPermissions.value = true;
    permissionsError.value = false;
    const response = await sanctumFetch('/api/permissions');
    permissionGroups.value = response.data || [];
  } catch (err) {
    console.error('Error loading permissions:', err);
    permissionsError.value = true;
  } finally {
    loadingPermissions.value = false;
  }
}

// Populate form when editing
watch(
  () => props.role,
  (newRole) => {
    if (newRole && props.mode === "edit") {
      formData.value = {
        name: newRole.name,
        permissions: newRole.permissions?.map(p => p.name) || [],
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
      props.mode === "create" ? "/api/roles" : `/api/roles/${props.role.name}`;

    const method = props.mode === "create" ? "POST" : "PUT";

    const response = await sanctumFetch(endpoint, {
      method,
      body: formData.value,
    });

    if (response.data) {
      const successMessage =
        props.mode === "create"
          ? "Role created successfully!"
          : "Role updated successfully!";
      toast.success(successMessage);
      navigateTo("/roles");
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage =
        err.response?._data?.message || err.message || `Failed to ${props.mode} role`;
      toast.error(errorMessage);
    }
    console.error(`Error ${props.mode}ing role:`, err);
  } finally {
    internalLoading.value = false;
  }
}

// Load permissions on mount
onMounted(async () => {
  await loadPermissions();
});

// Expose submit handler for keyboard shortcuts
defineExpose({
  handleSubmit,
});
</script>
