<template>
  <div class="mx-auto max-w-md space-y-6">
    <div class="flex flex-col items-start gap-y-5">
      <BackButton destination="/users" />

      <h1 class="page-title">Edit User</h1>
    </div>

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

    <div v-if="user" class="mt-20 space-y-4">
      <h6 class="text-muted-foreground text-sm font-medium tracking-tight">Account Information</h6>

      <div
        class="border-border text-foreground w-full overflow-x-scroll rounded-xl border p-4 text-sm tracking-tight"
      >
        <div class="flex flex-col gap-y-2">
          <div class="inline-flex gap-x-1.5">
            <span class="text-muted-foreground">User ID:</span>
            <span>{{ user.id }}</span>
          </div>

          <div class="inline-flex gap-x-1.5">
            <span class="text-muted-foreground">ULID:</span>
            <span>{{ user.ulid }}</span>
          </div>

          <div class="inline-flex gap-x-1.5">
            <span class="text-muted-foreground">Created:</span>
            <span>{{ $dayjs(user.created_at).format("MMM D, YYYY [at] h:mm A") }}</span>
          </div>

          <div class="inline-flex gap-x-1.5">
            <span class="text-muted-foreground">Last Seen:</span>
            <span>{{ user.last_seen ? $dayjs(user.last_seen).fromNow() : "Never" }}</span>
          </div>
        </div>
      </div>

      <div class="border-border text-foreground w-full overflow-x-scroll rounded-xl border p-4">
        <pre class="text-foreground/80 text-sm !leading-[1.5]">{{ user }}</pre>
      </div>
    </div>
  </div>
</template>

<script setup>
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
      user.value = response.data;
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
