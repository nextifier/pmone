<template>
  <div class="min-h-screen-offset mx-auto flex max-w-xl flex-col gap-y-5 pt-4 pb-16">
    <template v-if="user">
      <div class="flex flex-col gap-y-5">
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
      <LoadingState v-if="initialLoading" />
      <div v-else class="min-h-screen-offset flex w-full items-center justify-center">
        <div v-if="error" class="frame w-full">
          <div class="frame-panel">
            <div class="flex w-full flex-col items-center justify-center gap-y-4 text-center">
              <div
                class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
              >
                <div class="translate-y-1.5 -rotate-6">
                  <Icon name="hugeicons:file-empty-01" />
                </div>
                <div>
                  <Icon name="hugeicons:search-remove" />
                </div>
                <div class="translate-y-1.5 rotate-6">
                  <Icon name="hugeicons:user" />
                </div>
              </div>
              <div class="space-y-1">
                <h3 class="text-lg font-semibold tracking-tighter">{{ error }}</h3>
              </div>
              <NuxtLink
                to="/users/"
                class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              >
                <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
                <span>Back to Users</span>
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["admin", "master"],
  layout: "app",
});

const route = useRoute();
const { user: currentUser } = useSanctumAuth();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();
const { metaSymbol } = useShortcuts();
const { signalRefresh } = useDataRefresh();

// Refs
const formUserRef = ref(null);

// State
const loading = ref(false);
const deleting = ref(false);
const success = ref(null);
const errors = ref({});

const title = "Edit User";
const description = "";

usePageMeta("", {
  title: title,
  description: description,
});

// Permission checking using composable
const {
  isAdminOrMaster: canEditUsers,
  isAdminOrMaster: canDeleteUsers,
  isMaster,
} = usePermission();

// Fetch user data with lazy loading
const {
  data: userResponse,
  pending: initialLoading,
  error: userError,
  refresh: loadUser,
} = await useLazySanctumFetch(() => `/api/users/${route.params.username}`, {
  key: `user-edit-${route.params.username}`,
});

const user = computed(() => userResponse.value?.data || null);

// Fetch roles data with lazy loading
const { data: rolesResponse } = await useLazySanctumFetch(() => `/api/users/roles`, {
  key: "user-roles",
});

const roles = computed(() => rolesResponse.value?.data || []);

const error = computed(() => {
  if (!userError.value) return null;
  if (userError.value.statusCode === 404) return "User not found";
  if (userError.value.statusCode === 403) return "You do not have permission to view this user";
  return userError.value.message || "Failed to load user";
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

// Update user
async function updateUser(payload) {
  loading.value = true;
  success.value = null;
  errors.value = {};

  try {
    const sanctumFetch = useSanctumClient();

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

      // Signal that users list needs refresh
      signalRefresh("users-list");

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

  try {
    const sanctumFetch = useSanctumClient();
    await sanctumFetch(`/api/users/${user.value.username}`, {
      method: "DELETE",
    });

    // Signal that users list needs refresh
    signalRefresh("users-list");

    // Navigate back to users list
    navigateTo("/users");
  } catch (err) {
    console.error("Error deleting user:", err);
    toast.error(err.message || "Failed to delete user");
  } finally {
    deleting.value = false;
  }
}
</script>
