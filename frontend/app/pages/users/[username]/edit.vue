<template>
  <div class="mx-auto max-w-md space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:edit-user-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Edit User</h1>
      </div>

      <div v-if="user && canDeleteThisUser" class="flex items-center gap-2">
        <button
          @click="confirmDeleteUser"
          :disabled="deleting"
          class="border-destructive bg-destructive/10 text-destructive hover:bg-destructive/20 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
        >
          <Icon name="hugeicons:loading-01" v-if="deleting" class="size-4 animate-spin" />
          <Icon name="hugeicons:delete-02" v-else class="size-4" />
          {{ deleting ? "Deleting..." : "Delete User" }}
        </button>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="initialLoading" class="space-y-4">
      <div class="animate-pulse rounded-lg border p-6">
        <div class="bg-muted mb-4 h-6 w-1/3 rounded"></div>
        <div class="space-y-3">
          <div class="bg-muted h-4 w-full rounded"></div>
          <div class="bg-muted h-4 w-2/3 rounded"></div>
        </div>
      </div>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      class="border-destructive bg-destructive/10 text-destructive rounded-lg border p-4"
    >
      {{ error }}
    </div>

    <!-- Success message -->
    <div
      v-if="success"
      class="rounded-lg border border-green-500 bg-green-100 p-4 text-green-800 dark:bg-green-900 dark:text-green-300"
    >
      {{ success }}
    </div>

    <!-- Form -->
    <div v-if="user && !initialLoading">
      <FormProfile
        :initial-data="user"
        :roles="roles"
        :loading="loading"
        :errors="errors"
        :is-create="false"
        :show-password="canEditUsers"
        :show-account-settings="canEditUsers"
        :show-roles="canEditUsers"
        :show-images="true"
        :show-reset="true"
        submit-text="Update User"
        submit-loading-text="Updating.."
        @submit="updateUser"
        @reset="resetForm"
      />

      <!-- User Info (read-only) -->
      <div class="mt-6 space-y-4 rounded-lg border p-6">
        <h3 class="text-lg font-medium">Account Information</h3>

        <div class="bg-muted/50 rounded-lg p-4 text-sm">
          <div class="grid gap-2 sm:grid-cols-2">
            <div>
              <span class="text-muted-foreground">User ID:</span>
              <span class="ml-2 font-mono">{{ user.id }}</span>
            </div>
            <div>
              <span class="text-muted-foreground">ULID:</span>
              <span class="ml-2 font-mono text-xs">{{ user.ulid }}</span>
            </div>
            <div>
              <span class="text-muted-foreground">Created:</span>
              <span class="ml-2">{{
                $dayjs(user.created_at).format("MMM D, YYYY [at] h:mm A")
              }}</span>
            </div>
            <div>
              <span class="text-muted-foreground">Last Seen:</span>
              <span class="ml-2">{{
                user.last_seen ? $dayjs(user.last_seen).fromNow() : "Never"
              }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Back Button -->
      <div class="mt-6 flex justify-start">
        <NuxtLink
          to="/users"
          class="border-border hover:bg-muted inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm transition"
        >
          <Icon name="hugeicons:arrow-left-02" class="size-4" />
          Back to Users
        </NuxtLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import FormProfile from "@/components/FormProfile.vue";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const route = useRoute();
const { user: currentUser } = useSanctumAuth();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

// State
const user = ref(null);
const roles = ref([]);
const initialLoading = ref(true);
const loading = ref(false);
const deleting = ref(false);
const error = ref(null);
const success = ref(null);
const errors = ref({});

const title = "Edit User";
const description = "";

useSeoMeta({
  titleTemplate: "%s · %siteName",
  title: title,
  ogTitle: title,
  description: description,
  ogDescription: description,
  ogUrl: useAppConfig().app.url + route.fullPath,
  twitterCard: "summary_large_image",
});

// Computed
const canEditUsers = computed(() => {
  return currentUser.value?.roles?.some((role) => ["master", "admin"].includes(role));
});

const canDeleteUsers = computed(() => {
  return currentUser.value?.roles?.some((role) => ["master", "admin"].includes(role));
});

const isMaster = computed(() => {
  return currentUser.value?.roles?.includes("master");
});

const canDeleteThisUser = computed(() => {
  if (!canDeleteUsers.value) return false;
  if (!user.value) return false;
  // Admin cannot delete master users
  if (user.value.roles?.includes("master") && !isMaster.value) return false;
  // Cannot delete yourself
  if (user.value.username === currentUser.value?.username) return false;
  return true;
});

// Load user data
async function loadUser() {
  initialLoading.value = true;
  error.value = null;

  try {
    const response = await sanctumFetch(`/api/users/${route.params.username}`);

    if (response.data) {
      console.log('API response.data:', response.data);
      console.log('API response.data.roles:', response.data.roles);
      user.value = response.data;
      console.log('user.value after assignment:', user.value);
      console.log('user.value.roles after assignment:', user.value.roles);
    }
  } catch (err) {
    if (err.response?.status === 404) {
      error.value = "User not found";
    } else if (err.response?.status === 403) {
      error.value = "You do not have permission to view this user";
    } else {
      error.value = err.message || "Failed to load user";
    }
    console.error("Error loading user:", err);
  } finally {
    initialLoading.value = false;
  }
}

// Load roles
async function loadRoles() {
  try {
    const response = await sanctumFetch("/api/users/roles");
    roles.value = response.data;
  } catch (err) {
    console.error("Error loading roles:", err);
  }
}

// Update user
async function updateUser(payload) {
  loading.value = true;
  error.value = null;
  success.value = null;
  errors.value = {};

  try {
    // Remove empty values
    Object.keys(payload).forEach((key) => {
      if (payload[key] === "" || payload[key] === null) {
        delete payload[key];
      }
    });

    // If not admin/master, only allow certain fields
    if (!canEditUsers.value) {
      const allowedFields = [
        "name",
        "username",
        "email",
        "phone",
        "birth_date",
        "gender",
        "bio",
        "visibility",
        "tmp_profile_image",
        "tmp_cover_image",
      ];
      Object.keys(payload).forEach((key) => {
        if (!allowedFields.includes(key)) {
          delete payload[key];
        }
      });
    }

    const response = await sanctumFetch(`/api/users/${user.value.username}`, {
      method: "PUT",
      body: payload,
    });

    if (response.data) {
      user.value = response.data;
      success.value = "User updated successfully!";

      // Refresh user data
      setTimeout(() => {
        loadUser();
      }, 1000);
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      error.value = "Please fix the validation errors below.";
    } else {
      error.value = err.message || "Failed to update user";
    }
    console.error("Error updating user:", err);
  } finally {
    loading.value = false;
  }
}

// Reset form
function resetForm() {
  errors.value = {};
  error.value = null;
  success.value = null;
}

// Confirm delete user
async function confirmDeleteUser() {
  if (
    !confirm(`Are you sure you want to delete ${user.value.name}? This action cannot be undone.`)
  ) {
    return;
  }

  deleting.value = true;
  error.value = null;

  try {
    await sanctumFetch(`/api/users/${user.value.username}`, {
      method: "DELETE",
    });

    // Navigate back to users list
    navigateTo("/users");
  } catch (err) {
    error.value = err.message || "Failed to delete user";
    console.error("Error deleting user:", err);
  } finally {
    deleting.value = false;
  }
}

// Load data on mount
onMounted(async () => {
  await Promise.all([loadUser(), loadRoles()]);
});
</script>
