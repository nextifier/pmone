<template>
  <div class="mx-auto max-w-5xl space-y-6 pt-4 pb-16">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="page-title">Contact Form Inbox</h1>
      <button
        @click="refresh"
        :disabled="pending"
        class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
      >
        <Icon
          :name="pending ? 'lucide:loader-2' : 'lucide:refresh-cw'"
          class="size-4 shrink-0"
          :class="{ 'animate-spin': pending }"
        />
        <span>Refresh</span>
      </button>
    </div>

    <!-- Filters -->
    <div class="frame">
      <div class="frame-panel">
        <div class="flex flex-col gap-3 sm:flex-row">
          <!-- Search -->
          <div class="flex-1">
            <Input
              v-model="searchQuery"
              type="search"
              placeholder="Search submissions..."
              class="w-full"
            />
          </div>

          <!-- Status Filter -->
          <Select v-model="selectedStatus">
            <SelectTrigger class="w-full sm:w-44">
              <SelectValue placeholder="All Statuses" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Statuses</SelectItem>
              <SelectItem value="new">New</SelectItem>
              <SelectItem value="in_progress">In Progress</SelectItem>
              <SelectItem value="completed">Completed</SelectItem>
              <SelectItem value="archived">Archived</SelectItem>
            </SelectContent>
          </Select>

          <!-- Project Filter -->
          <Select v-model="selectedProjectId">
            <SelectTrigger class="w-full sm:w-44">
              <SelectValue placeholder="All Projects" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Projects</SelectItem>
              <SelectItem
                v-for="project in projects"
                :key="project.id"
                :value="project.id.toString()"
              >
                {{ project.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>

    <!-- Submissions List -->
    <div v-if="pending && !submissions.length" class="flex items-center justify-center py-12">
      <Spinner class="size-6" />
    </div>

    <div v-else-if="error" class="frame">
      <div class="frame-panel">
        <div class="text-destructive text-center">{{ error }}</div>
      </div>
    </div>

    <div v-else-if="!submissions.length" class="frame">
      <div class="frame-panel">
        <div class="flex flex-col items-center justify-center gap-4 py-12 text-center">
          <Icon name="hugeicons:mail-open-01" class="text-muted-foreground size-12" />
          <div>
            <h3 class="font-semibold">No submissions found</h3>
            <p class="text-muted-foreground text-sm">
              {{
                hasActiveFilters
                  ? "Try adjusting your filters."
                  : "No contact form submissions yet."
              }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="submission in submissions"
        :key="submission.id"
        class="frame hover:border-primary/50 cursor-pointer transition"
        @click="viewSubmission(submission)"
      >
        <div class="frame-panel">
          <div class="flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1 space-y-2">
              <!-- Subject & Status -->
              <div class="flex items-center gap-2">
                <h3 class="font-medium tracking-tight">
                  {{ submission.subject || "No Subject" }}
                </h3>
                <span
                  :class="getStatusConfig(submission.status).color"
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                >
                  {{ getStatusConfig(submission.status).label }}
                </span>
              </div>

              <!-- Preview Data -->
              <div class="text-muted-foreground space-y-1 text-sm">
                <div v-if="submission.form_data_preview.name">
                  <span class="font-medium">Name:</span> {{ submission.form_data_preview.name }}
                </div>
                <div v-if="submission.form_data_preview.email">
                  <span class="font-medium">Email:</span> {{ submission.form_data_preview.email }}
                </div>
                <div v-if="submission.form_data_preview.phone">
                  <span class="font-medium">Phone:</span> {{ submission.form_data_preview.phone }}
                </div>
              </div>

              <!-- Meta Info -->
              <div
                class="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
              >
                <div class="flex items-center gap-1">
                  <Icon name="lucide:folder" class="size-3" />
                  <span>{{ submission.project?.name || "Unknown Project" }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <Icon name="lucide:calendar" class="size-3" />
                  <span>{{ $dayjs(submission.created_at).format("MMM D, YYYY [at] h:mm A") }}</span>
                </div>
                <div v-if="submission.followed_up_at" class="flex items-center gap-1">
                  <Icon name="lucide:check-circle" class="size-3" />
                  <span>Followed up {{ $dayjs(submission.followed_up_at).fromNow() }}</span>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
              <button
                @click.stop="quickUpdateStatus(submission)"
                class="hover:bg-muted rounded-lg p-2 transition"
                :title="submission.status === 'new' ? 'Mark as In Progress' : 'Mark as Completed'"
              >
                <Icon
                  :name="
                    submission.status === 'new'
                      ? 'lucide:play-circle'
                      : submission.status === 'in_progress'
                        ? 'lucide:check-circle'
                        : 'lucide:archive'
                  "
                  class="size-4"
                />
              </button>
              <Icon name="lucide:chevron-right" class="text-muted-foreground size-4" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="meta && meta.last_page > 1" class="flex items-center justify-center gap-2">
      <button
        @click="goToPage(meta.current_page - 1)"
        :disabled="meta.current_page === 1 || pending"
        class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50"
      >
        Previous
      </button>
      <span class="text-muted-foreground text-sm">
        Page {{ meta.current_page }} of {{ meta.last_page }}
      </span>
      <button
        @click="goToPage(meta.current_page + 1)"
        :disabled="meta.current_page === meta.last_page || pending"
        class="border-border hover:bg-muted rounded-md border px-3 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("inbox");

const client = useSanctumClient();
const router = useRouter();

// State
const submissions = ref([]);
const projects = ref([]);
const meta = ref(null);
const pending = ref(false);
const error = ref(null);

// Filters
const searchQuery = ref("");
const selectedStatus = ref("all");
const selectedProjectId = ref("all");
const currentPage = ref(1);

// Computed
const hasActiveFilters = computed(() => {
  return !!searchQuery.value || selectedStatus.value !== "all" || selectedProjectId.value !== "all";
});

// Fetch projects for filter
async function fetchProjects() {
  try {
    const response = await client("/api/projects", {
      params: { client_only: true },
    });

    // Handle response - could be direct array or nested in data
    if (Array.isArray(response)) {
      projects.value = response;
    } else if (response && response.data) {
      projects.value = Array.isArray(response.data) ? response.data : [];
    } else {
      projects.value = [];
    }
  } catch (err) {
    console.error("Failed to fetch projects:", err);
    projects.value = [];
  }
}

// Fetch submissions
async function fetchSubmissions() {
  pending.value = true;
  error.value = null;

  try {
    const params = {
      page: currentPage.value,
      per_page: 15,
    };

    if (searchQuery.value) {
      params.filter_search = searchQuery.value;
    }

    if (selectedStatus.value && selectedStatus.value !== "all") {
      params.filter_status = selectedStatus.value;
    }

    if (selectedProjectId.value && selectedProjectId.value !== "all") {
      params.filter_project = selectedProjectId.value;
    }

    const response = await client("/api/contact-form-submissions", { params });

    submissions.value = response.data || [];
    meta.value = response.meta || null;
  } catch (err) {
    console.error("Failed to fetch submissions:", err);
    error.value = err.message || "Failed to load submissions";
  } finally {
    pending.value = false;
  }
}

// Refresh
function refresh() {
  currentPage.value = 1;
  fetchSubmissions();
}

// Watch filters
watch([searchQuery, selectedStatus, selectedProjectId], () => {
  currentPage.value = 1;
  fetchSubmissions();
});

// Pagination
function goToPage(page) {
  if (page < 1 || (meta.value && page > meta.value.last_page)) return;
  currentPage.value = page;
  fetchSubmissions();
}

// View submission detail
function viewSubmission(submission) {
  // Navigate to detail page (can be created later)
  router.push(`/inbox/${submission.ulid}`);
}

// Quick status update
async function quickUpdateStatus(submission) {
  const statusMap = {
    new: "in_progress",
    in_progress: "completed",
    completed: "archived",
    archived: "new",
  };

  const newStatus = statusMap[submission.status] || "in_progress";

  try {
    await client(`/api/contact-form-submissions/${submission.ulid}/status`, {
      method: "PATCH",
      body: { status: newStatus },
    });

    toast.success("Status updated successfully");
    refresh();
  } catch (err) {
    console.error("Failed to update status:", err);
    toast.error("Failed to update status");
  }
}

// Status badge config
function getStatusConfig(status) {
  const configs = {
    new: {
      label: "New",
      color: "bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400",
    },
    in_progress: {
      label: "In Progress",
      color: "bg-yellow-500/10 text-yellow-600 dark:bg-yellow-500/20 dark:text-yellow-400",
    },
    completed: {
      label: "Completed",
      color: "bg-green-500/10 text-green-600 dark:bg-green-500/20 dark:text-green-400",
    },
    archived: {
      label: "Archived",
      color: "bg-gray-500/10 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400",
    },
  };
  return configs[status] || configs.new;
}

// Lifecycle
onMounted(() => {
  fetchProjects();
  fetchSubmissions();
});
</script>
