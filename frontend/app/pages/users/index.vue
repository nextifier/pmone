<template>
  <div class="mx-auto max-w-7xl space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:user-group" class="size-5 sm:size-6" />
        <h1 class="page-title">User Management</h1>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="refreshUsers"
          :disabled="loading"
          class="border-border hover:bg-muted flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-sm tracking-tight transition active:scale-98 disabled:opacity-50"
        >
          <Icon name="hugeicons:reload" class="size-4" :class="{ 'animate-spin': loading }" />
          Refresh
        </button>

        <NuxtLink
          v-if="canCreateUsers"
          to="/users/create"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition active:scale-98"
        >
          <Icon name="hugeicons:user-add-01" class="size-4" />
          Create User
        </NuxtLink>
      </div>
    </div>

    <!-- Filters -->
    <div class="mt-4 flex flex-wrap items-center gap-4">
      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Status:</label>
        <select
          v-model="filters.status"
          @change="loadUsers(1)"
          class="bg-background rounded border px-2 py-1 text-sm"
        >
          <option value="">All</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
          <option value="suspended">Suspended</option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Role:</label>
        <select
          v-model="filters.role"
          @change="loadUsers(1)"
          class="bg-background rounded border px-2 py-1 text-sm"
        >
          <option value="">All</option>
          <option v-for="role in roles" :key="role.id" :value="role.name">
            {{ role.name }}
          </option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Search:</label>
        <input
          v-model="filters.search"
          @input="debouncedSearch"
          placeholder="Search by name, email, or username..."
          class="bg-background w-64 rounded border px-2 py-1 text-sm"
        />
      </div>

      <div class="flex items-center gap-2">
        <label class="text-sm font-medium">Per page:</label>
        <select
          v-model="filters.perPage"
          @change="loadUsers(1)"
          class="bg-background rounded border px-2 py-1 text-sm"
        >
          <option value="15">15</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
    </div>

    <!-- Error message -->
    <div
      v-if="error"
      class="border-destructive bg-destructive/10 text-destructive rounded-lg border p-4"
    >
      {{ error }}
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading && users.length === 0" class="space-y-3">
      <div v-for="i in 5" :key="i" class="animate-pulse rounded-lg border p-4">
        <div class="bg-muted mb-2 h-4 w-1/4 rounded"></div>
        <div class="bg-muted h-3 w-3/4 rounded"></div>
      </div>
    </div>

    <!-- Users table -->
    <div v-else-if="users.length > 0" class="overflow-hidden rounded-lg border">
      <div class="overflow-x-auto">
        <table class="w-full table-auto">
          <thead class="bg-muted/50 border-b">
            <tr>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                User
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Role
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Status
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Last Seen
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Created
              </th>
              <th
                v-if="canEditUsers || canDeleteUsers"
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-border divide-y text-sm tracking-tight" v-auto-animate>
            <tr
              v-for="user in users"
              :key="user.id"
              :class="canEditUser(user) ? 'hover:bg-muted/50 cursor-pointer' : 'cursor-default'"
              @click="goToUserDetail(user)"
            >
              <!-- User info -->
              <td class="px-4 py-3">
                <AuthUserInfo :user="user" />
              </td>

              <!-- Role -->
              <td class="px-4 py-3">
                <div v-if="user.roles && user.roles.length > 0" class="flex flex-wrap gap-1">
                  <span class="text-sm tracking-tight capitalize">{{ user.roles.join(", ") }}</span>
                </div>
                <span v-else class="text-muted-foreground text-sm">No role</span>
              </td>

              <!-- Status -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-x-1.5">
                  <span
                    :class="{
                      'bg-success': user.status === 'active',
                      'bg-destructive': user.status === 'inactive',
                      'bg-warn': user.status === 'suspended',
                    }"
                    class="size-2 shrink-0 rounded-full"
                  >
                  </span>
                  <span class="text-sm tracking-tight capitalize">
                    {{ user.status }}
                  </span>
                </div>
              </td>

              <!-- Last seen -->
              <td class="px-4 py-3">
                <div v-if="user.last_seen" class="text-sm">
                  <div v-tippy="$dayjs(user.last_seen).format('MMMM D, YYYY [at] h:mm A')">
                    {{ $dayjs(user.last_seen).fromNow() }}
                  </div>
                </div>
                <span v-else class="text-muted-foreground text-sm">Never</span>
              </td>

              <!-- Created -->
              <td class="px-4 py-3">
                <div
                  class="text-sm"
                  v-tippy="$dayjs(user.created_at).format('MMMM D, YYYY [at] h:mm A')"
                >
                  {{ $dayjs(user.created_at).format("MMM D, YYYY") }}
                </div>
              </td>

              <!-- Actions -->
              <td v-if="canEditUsers || canDeleteUsers" class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <NuxtLink
                    v-if="canEditUser(user)"
                    :to="`/users/${user.username}/edit`"
                    @click.stop
                    class="hover:bg-accent rounded p-1.5 text-sm transition"
                    v-tippy="'Edit user'"
                  >
                    <Icon name="hugeicons:edit-02" class="size-4" />
                  </NuxtLink>

                  <button
                    v-if="canDeleteUser(user)"
                    @click.stop="confirmDeleteUser(user)"
                    class="hover:bg-destructive/10 text-destructive rounded p-1.5 text-sm transition"
                    v-tippy="'Delete user'"
                  >
                    <Icon name="hugeicons:delete-02" class="size-4" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!loading" class="py-12 text-center">
      <Icon name="hugeicons:user-group" class="text-muted-foreground mx-auto mb-4 size-12" />
      <h3 class="mb-2 text-lg font-medium">No users found</h3>
      <p class="text-muted-foreground">
        {{
          filters.status || filters.role || filters.search
            ? "Try adjusting your filters"
            : "No users available"
        }}
      </p>
    </div>

    <!-- Pagination -->
    <div v-if="meta.total > 0" class="flex items-center justify-between border-t pt-4">
      <div class="text-muted-foreground text-sm">
        Showing {{ (meta.current_page - 1) * meta.per_page + 1 }} to
        {{ Math.min(meta.current_page * meta.per_page, meta.total) }} of {{ meta.total }} results
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="loadUsers(meta.current_page - 1)"
          :disabled="meta.current_page <= 1 || loading"
          class="hover:bg-accent rounded border px-3 py-1 text-sm disabled:opacity-50"
        >
          Previous
        </button>

        <span class="text-sm"> Page {{ meta.current_page }} of {{ meta.last_page }} </span>

        <button
          @click="loadUsers(meta.current_page + 1)"
          :disabled="meta.current_page >= meta.last_page || loading"
          class="hover:bg-accent rounded border px-3 py-1 text-sm disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "staff-admin-master"],
  layout: "app",
});

defineOptions({
  name: "users",
});

usePageMeta("users");

const { user: currentUser } = useSanctumAuth();
const sanctumFetch = useSanctumClient();
const { $dayjs } = useNuxtApp();

// State
const users = ref([]);
const roles = ref([]);
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
});
const loading = ref(false);
const error = ref(null);

// Filters
const filters = reactive({
  status: "",
  role: "",
  search: "",
  perPage: 15,
});

// Permissions
const canCreateUsers = computed(() => {
  return currentUser.value?.roles?.some((role) => ["master", "admin"].includes(role));
});

const canEditUsers = computed(() => {
  return currentUser.value?.roles?.some((role) => ["master", "admin"].includes(role));
});

const canDeleteUsers = computed(() => {
  return currentUser.value?.roles?.some((role) => ["master", "admin"].includes(role));
});

const isMaster = computed(() => {
  return currentUser.value?.roles?.includes("master");
});

// Check if user can edit/delete specific user
const canEditUser = (user) => {
  if (!canEditUsers.value) return false;
  // Admin cannot edit master users
  if (user.roles?.includes("master") && !isMaster.value) return false;
  return true;
};

const canDeleteUser = (user) => {
  if (!canDeleteUsers.value) return false;
  // Admin cannot delete master users
  if (user.roles?.includes("master") && !isMaster.value) return false;
  // Cannot delete yourself
  if (user.username === currentUser.value?.username) return false;
  return true;
};

// Debounced search
let searchTimeout;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadUsers(1);
  }, 500);
};

// Load users
async function loadUsers(page = 1) {
  loading.value = true;
  error.value = null;

  try {
    const params = new URLSearchParams({
      page: page.toString(),
      per_page: filters.perPage.toString(),
    });

    if (filters.status) {
      params.append("filter[status]", filters.status);
    }

    if (filters.search) {
      params.append("filter[search]", filters.search);
    }

    if (filters.role) {
      params.append("filter[role]", filters.role);
    }

    // Add sorting
    params.append("sort", "-created_at");

    const response = await sanctumFetch(`/api/users?${params.toString()}`);

    if (response.data) {
      users.value = response.data;
      meta.value = response.meta;
    }
  } catch (err) {
    error.value = err.message || "Failed to load users";
    console.error("Error loading users:", err);
  } finally {
    loading.value = false;
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

// Refresh users
function refreshUsers() {
  loadUsers(meta.value.current_page);
}

// Navigate to user detail
function goToUserDetail(user) {
  if (canEditUser(user)) {
    navigateTo(`/users/${user.username}/edit`);
  }
}

// Confirm delete user
async function confirmDeleteUser(user) {
  if (!confirm(`Are you sure you want to delete ${user.name}? This action cannot be undone.`)) {
    return;
  }

  try {
    const response = await sanctumFetch(`/api/users/${user.username}`, {
      method: "DELETE",
    });

    toast.success(response?.message);

    // Reload users after deletion
    await loadUsers(meta.value.current_page);

    // Show success message (you might want to use a toast notification)
    console.log("User deleted successfully");
  } catch (err) {
    error.value = err.message || "Failed to delete user";
    console.error("Error deleting user:", err);
  }
}

// Load data on mount
onMounted(async () => {
  await Promise.all([loadUsers(), loadRoles()]);
});
</script>
