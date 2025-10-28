<template>
  <div class="min-h-screen-offset mx-auto max-w-xl space-y-9">
    <template v-if="user">
      <div class="flex flex-col gap-y-6">
        <div class="flex w-full items-center justify-between">
          <BackButton destination="/users" />

          <button
            @click="formUserRef?.handleSubmit()"
            :disabled="loading"
            class="text-primary-foreground hover:bg-primary/80 bg-primary flex items-center justify-center gap-x-1 rounded-lg px-3 py-1.5 text-sm font-medium tracking-tight transition active:scale-98 disabled:opacity-50"
          >
            <Spinner v-if="loading" />
            <span>Save</span>
          </button>
        </div>

        <h1 class="page-title">Edit User</h1>
      </div>

      <FormUser
        ref="formUserRef"
        :initial-data="user"
        :roles="roles"
        :loading="loading"
        :errors="errors"
        :is-create="false"
        :show-password="canEditUsers"
        :show-account-settings="canEditUsers"
        :show-roles="canEditUsers"
        :show-images="true"
        submit-text="Update User"
        submit-loading-text="Updating.."
        @submit="updateUser"
      />

      <div
        v-if="user"
        class="*:bg-muted text-muted-foreground mt-20 flex flex-wrap gap-x-2 gap-y-2.5 text-sm tracking-tight *:rounded-md *:px-2 *:py-1"
      >
        <span
          >ID: <span class="text-foreground">{{ user.id }}</span></span
        >
        <span
          >ULID: <span class="text-foreground">{{ user.ulid }}</span></span
        >
        <span
          >Created:
          <span class="text-foreground">{{
            $dayjs(user.created_at).format("MMM D, YYYY [at] h:mm A")
          }}</span></span
        >
        <span
          >Last seen:
          <span class="text-foreground">{{
            user.last_seen ? $dayjs(user.last_seen).fromNow() : "Never"
          }}</span></span
        >
      </div>
    </template>

    <template v-else>
      <div class="min-h-screen-offset flex w-full items-center justify-center">
        <div class="flex items-center gap-1.5">
          <Spinner class="size-4 shrink-0" />
          <span class="tracking-tight">Loading</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const route = useRoute();
const { user: currentUser } = useSanctumAuth();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();
const { metaSymbol } = useShortcuts();

// Refs
const formUserRef = ref(null);

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
      toast.success("User updated successfully!");

      // Navigate to users list
      navigateTo("/users");
    }
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      const firstErrorMessage = err.response._data.errors[firstErrorField][0];
      toast.error(firstErrorMessage || "Please fix the validation errors.");
    } else {
      const errorMessage = err.response?._data?.message || err.message || "Failed to update user";
      toast.error(errorMessage);
    }
    console.error("Error updating user:", err);
  } finally {
    loading.value = false;
  }
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
