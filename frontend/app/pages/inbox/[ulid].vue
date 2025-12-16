<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl xl:max-w-5xl">
    <!-- Back Button & Actions -->
    <div class="flex items-center justify-between">
      <NuxtLink
        to="/inbox"
        class="text-muted-foreground hover:text-foreground flex items-center gap-1 text-sm transition"
      >
        <Icon name="lucide:arrow-left" class="size-4" />
        <span>Back to Inbox</span>
      </NuxtLink>

      <div v-if="submission" class="flex items-center gap-2">
        <!-- Mark as Followed Up -->
        <button
          v-if="!submission.followed_up_at"
          @click="markAsFollowedUp"
          :disabled="updating"
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-3 py-1.5 text-sm tracking-tight transition active:scale-98 disabled:opacity-50"
        >
          <Icon name="lucide:check-circle" class="size-4" />
          <span>Mark as Followed Up</span>
        </button>

        <!-- Delete -->
        <button
          @click="confirmDelete"
          :disabled="updating"
          class="text-destructive hover:bg-destructive/10 flex items-center gap-x-1 rounded-md px-3 py-1.5 text-sm tracking-tight transition active:scale-98 disabled:opacity-50"
        >
          <Icon name="lucide:trash-2" class="size-4" />
          <span>Delete</span>
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <LoadingState v-if="pending" label="Loading submission.." />

    <!-- Error State -->
    <div v-else-if="error" class="frame">
      <div class="frame-panel">
        <div class="text-destructive text-center">{{ error }}</div>
      </div>
    </div>

    <!-- Submission Detail -->
    <template v-else-if="submission">
      <!-- Header Info -->
      <div class="frame">
        <div class="frame-header">
          <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold tracking-tight">
              {{ submission.subject || "No Subject" }}
            </h1>
            <span
              :class="getStatusConfig(submission.status).color"
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            >
              {{ getStatusConfig(submission.status).label }}
            </span>
          </div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                Project
              </div>
              <div class="mt-1 font-medium">
                {{ submission.project?.name || "Unknown" }}
              </div>
            </div>
            <div>
              <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                Submitted
              </div>
              <div class="mt-1">
                {{ $dayjs(submission.created_at).format("MMM D, YYYY [at] h:mm A") }}
              </div>
            </div>
            <div>
              <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                IP Address
              </div>
              <div class="mt-1 font-mono text-sm">
                {{ submission.ip_address || "N/A" }}
              </div>
            </div>
            <div>
              <div class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                Followed Up
              </div>
              <div class="mt-1">
                <template v-if="submission.followed_up_at">
                  {{ $dayjs(submission.followed_up_at).format("MMM D, YYYY") }}
                  <span v-if="submission.followed_up_by_user" class="text-muted-foreground text-sm">
                    by {{ submission.followed_up_by_user.name }}
                  </span>
                </template>
                <span v-else class="text-muted-foreground">Not yet</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Update -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Status</div>
        </div>
        <div class="frame-panel">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-for="status in statuses"
              :key="status.value"
              @click="updateStatus(status.value)"
              :disabled="updating || submission.status === status.value"
              :class="[
                'rounded-lg px-4 py-2 text-sm font-medium tracking-tight transition active:scale-98',
                submission.status === status.value
                  ? 'bg-primary text-primary-foreground'
                  : 'border-border hover:bg-muted border',
              ]"
            >
              {{ status.label }}
            </button>
          </div>
        </div>
      </div>

      <!-- Form Data -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Form Data</div>
        </div>
        <div class="frame-panel">
          <div class="divide-border divide-y">
            <div
              v-for="(value, key) in submission.form_data"
              :key="key"
              class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:gap-4"
            >
              <div class="text-muted-foreground w-full text-sm font-medium sm:w-32 sm:shrink-0">
                {{ formatFieldLabel(key) }}
              </div>
              <div class="flex-1">
                <template v-if="key === 'email'">
                  <a
                    :href="`mailto:${value}`"
                    class="text-primary hover:underline"
                  >
                    {{ value }}
                  </a>
                </template>
                <template v-else-if="key === 'phone'">
                  <div class="flex items-center gap-2">
                    <FlagComponent
                      v-if="getCountryFromPhone(value)"
                      v-tippy="getCountryFromPhone(value)?.name"
                      :country="getCountryFromPhone(value)?.code"
                      class="cursor-help"
                    />
                    <a
                      :href="`https://wa.me/${formatWhatsAppNumber(value)}`"
                      target="_blank"
                      class="text-primary hover:underline"
                    >
                      {{ value }}
                    </a>
                  </div>
                </template>
                <template v-else-if="key === 'message'">
                  <div class="whitespace-pre-wrap">{{ value }}</div>
                </template>
                <template v-else>
                  {{ value }}
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- User Agent -->
      <div v-if="submission.user_agent" class="frame">
        <div class="frame-header">
          <div class="frame-title">User Agent</div>
        </div>
        <div class="frame-panel">
          <code class="text-muted-foreground text-xs break-all">
            {{ submission.user_agent }}
          </code>
        </div>
      </div>
    </template>

    <!-- Delete Confirmation Dialog -->
    <Dialog v-model:open="showDeleteDialog">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Submission</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete this submission? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button variant="outline" @click="showDeleteDialog = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="deleteSubmission" :disabled="updating">
            Delete
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import FlagComponent from "@/components/FlagComponent.vue";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

const route = useRoute();
const router = useRouter();
const client = useSanctumClient();

const ulid = computed(() => route.params.ulid);
const updating = ref(false);
const showDeleteDialog = ref(false);

// Fetch submission detail
const {
  data: submissionResponse,
  pending,
  error: fetchError,
  refresh,
} = await useLazySanctumFetch(() => `/api/contact-form-submissions/${ulid.value}`, {
  key: `inbox-submission-${ulid.value}`,
});

const submission = computed(() => submissionResponse.value?.data || null);
const error = computed(
  () => fetchError.value?.message || (fetchError.value ? "Failed to load submission" : null)
);

// Page meta
usePageMeta("inbox");

// Phone country helper
const { getCountryFromPhone } = usePhoneCountry();

// Status options
const statuses = [
  { value: "new", label: "New" },
  { value: "in_progress", label: "In Progress" },
  { value: "completed", label: "Completed" },
  { value: "archived", label: "Archived" },
];

// Update status
async function updateStatus(newStatus) {
  if (!submission.value || submission.value.status === newStatus) return;

  updating.value = true;
  try {
    await client(`/api/contact-form-submissions/${ulid.value}/status`, {
      method: "PATCH",
      body: { status: newStatus },
    });
    toast.success("Status updated successfully");
    refresh();
  } catch (err) {
    console.error("Failed to update status:", err);
    toast.error("Failed to update status");
  } finally {
    updating.value = false;
  }
}

// Mark as followed up
async function markAsFollowedUp() {
  updating.value = true;
  try {
    await client(`/api/contact-form-submissions/${ulid.value}/follow-up`, {
      method: "PATCH",
    });
    toast.success("Marked as followed up");
    refresh();
  } catch (err) {
    console.error("Failed to mark as followed up:", err);
    toast.error("Failed to mark as followed up");
  } finally {
    updating.value = false;
  }
}

// Delete submission
function confirmDelete() {
  showDeleteDialog.value = true;
}

async function deleteSubmission() {
  updating.value = true;
  try {
    await client(`/api/contact-form-submissions/${ulid.value}`, {
      method: "DELETE",
    });
    toast.success("Submission deleted");
    router.push("/inbox");
  } catch (err) {
    console.error("Failed to delete submission:", err);
    toast.error("Failed to delete submission");
  } finally {
    updating.value = false;
    showDeleteDialog.value = false;
  }
}

// Helpers
function formatFieldLabel(key) {
  return key
    .replace(/_/g, " ")
    .replace(/\b\w/g, (l) => l.toUpperCase());
}

function formatWhatsAppNumber(phone) {
  // Remove non-digit characters except leading +
  let cleaned = phone.replace(/[^\d+]/g, "");
  // Remove leading + if present
  cleaned = cleaned.replace(/^\+/, "");
  // Remove leading 0 for Indonesian numbers
  if (cleaned.startsWith("0")) {
    cleaned = "62" + cleaned.substring(1);
  }
  return cleaned;
}

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
</script>
