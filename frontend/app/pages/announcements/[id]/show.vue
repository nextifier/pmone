<template>
  <div class="mx-auto max-w-2xl space-y-6 pt-4 pb-16">
    <div class="flex flex-wrap items-start justify-between gap-x-2.5 gap-y-4">
      <div class="flex flex-col items-start gap-y-4">
        <ButtonBack destination="/announcements" force-destination />

        <div class="flex items-center gap-x-2.5">
          <Icon name="hugeicons:notification-02" class="size-5 shrink-0 sm:size-6" />
          <h1 class="page-title">{{ announcement?.title || "Announcement" }}</h1>
        </div>
        <p class="page-description">
          Read-only detail of how this announcement is configured.
        </p>
      </div>

      <Button
        v-if="canEdit && announcement"
        as-child
        size="sm"
        class="shrink-0"
      >
        <NuxtLink :to="`/announcements/${announcement.id}/edit`">
          <Icon name="lucide:pencil" class="size-4 shrink-0" />
          Edit
        </NuxtLink>
      </Button>
    </div>

    <div v-if="fetching" class="space-y-4">
      <Skeleton class="h-32 w-full rounded-xl" />
      <Skeleton class="h-40 w-full rounded-xl" />
      <Skeleton class="h-32 w-full rounded-xl" />
    </div>

    <div
      v-else-if="!announcement"
      class="frame"
    >
      <div class="frame-panel items-center gap-y-2 py-12 text-center">
        <Icon name="hugeicons:notification-off-02" class="text-muted-foreground size-8" />
        <p class="text-sm font-medium tracking-tight">Announcement not found</p>
        <p class="text-muted-foreground text-sm tracking-tight">
          It may have been removed or moved to trash.
        </p>
        <Button as-child variant="outline" size="sm" class="mt-2">
          <NuxtLink to="/announcements">Back to Announcements</NuxtLink>
        </Button>
      </div>
    </div>

    <template v-else>
      <!-- Details -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Details</div>
          <div class="frame-description">Status, type, audience, and behavior.</div>
        </div>
        <div class="frame-panel gap-y-6">
          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="STATUS_VARIANT[announcement.status] || 'muted'" class="capitalize">
              {{ announcement.status }}
            </Badge>
            <Badge variant="outline" class="capitalize">
              {{ announcement.type }}
            </Badge>
            <Badge :variant="announcement.is_global ? 'info' : 'muted'">
              {{ announcement.is_global ? "Global" : "Targeted" }}
            </Badge>
          </div>

          <p
            v-if="announcement.description"
            class="text-foreground text-sm tracking-tight text-pretty"
          >
            {{ announcement.description }}
          </p>

          <div class="grid grid-cols-2 gap-3 text-sm tracking-tight">
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Schedule</p>
              <p>{{ scheduleLabel(announcement.start_time, announcement.end_time) }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Dismissible</p>
              <p>{{ announcement.is_dismissible ? "Yes" : "No" }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Order</p>
              <p class="tabular-nums">{{ announcement.order_column ?? 0 }}</p>
            </div>
            <div>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Dismissed by</p>
              <p class="tabular-nums">{{ announcement.dismissals_count ?? 0 }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Targeting -->
      <div v-if="!announcement.is_global" class="frame">
        <div class="frame-header">
          <div class="frame-title">Targeting</div>
          <div class="frame-description">Who is eligible to see this announcement.</div>
        </div>
        <div class="frame-panel gap-y-6">
          <div class="space-y-2">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Roles</p>
            <div v-if="announcement.target_roles?.length" class="flex flex-wrap gap-2">
              <Badge
                v-for="role in announcement.target_roles"
                :key="role"
                variant="outline"
                class="capitalize"
              >
                {{ role }}
              </Badge>
            </div>
            <p v-else class="text-muted-foreground text-sm tracking-tight">No roles targeted.</p>
          </div>

          <div class="space-y-2">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Specific users</p>
            <ul
              v-if="announcement.target_users?.length"
              class="space-y-1 text-sm tracking-tight"
            >
              <li v-for="user in announcement.target_users" :key="user.id">
                {{ user.name }}
                <span v-if="user.email" class="text-muted-foreground">({{ user.email }})</span>
              </li>
            </ul>
            <p v-else class="text-muted-foreground text-sm tracking-tight">No specific users.</p>
          </div>

          <div class="space-y-2">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Specific events</p>
            <div v-if="announcement.target_events?.length" class="flex flex-wrap gap-2">
              <Badge
                v-for="event in announcement.target_events"
                :key="event.id"
                variant="muted"
              >
                {{ event.title || event.name }}
              </Badge>
            </div>
            <p v-else class="text-muted-foreground text-sm tracking-tight">No specific events.</p>
          </div>

          <div class="space-y-2">
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">Specific projects</p>
            <div v-if="announcement.target_projects?.length" class="flex flex-wrap gap-2">
              <Badge
                v-for="project in announcement.target_projects"
                :key="project.id"
                variant="muted"
              >
                {{ project.name }}
              </Badge>
            </div>
            <p v-else class="text-muted-foreground text-sm tracking-tight">No specific projects.</p>
          </div>
        </div>
      </div>

      <!-- Calls to action -->
      <div v-if="announcement.cta_actions?.length" class="frame">
        <div class="frame-header">
          <div class="frame-title">Calls to action</div>
          <div class="frame-description">Buttons and links shown with the announcement.</div>
        </div>
        <div class="frame-panel gap-y-3">
          <div
            v-for="(cta, idx) in announcement.cta_actions"
            :key="idx"
            class="bg-muted/30 flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
          >
            <div class="flex min-w-0 flex-col gap-0.5">
              <span class="flex items-center gap-1.5 text-sm font-medium tracking-tight">
                <Icon v-if="cta.icon" :name="cta.icon" class="size-4 shrink-0" />
                {{ cta.label }}
              </span>
              <span class="text-muted-foreground truncate text-sm tracking-tight">{{ cta.url }}</span>
            </div>
            <Badge variant="outline">{{ CTA_STYLE_LABEL[cta.style] || cta.style }}</Badge>
          </div>
        </div>
      </div>

      <!-- Preview -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Preview</div>
          <div class="frame-description">How this looks on a user dashboard.</div>
        </div>
        <div class="frame-panel">
          <AnnouncementPreviewCard :announcement="announcement" />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["announcements.read"],
  layout: "app",
});

usePageMeta(null, { title: "Announcement" });

const route = useRoute();
const client = useSanctumClient();
const { hasPermission } = usePermission();

const canEdit = computed(() => hasPermission("announcements.update"));

const fetching = ref(true);
const announcement = ref(null);

const STATUS_VARIANT = {
  published: "success",
  draft: "warning",
  archived: "muted",
};

const CTA_STYLE_LABEL = {
  link: "Link",
  "button-primary": "Primary button",
  "button-outline": "Outline button",
};

const formatScheduleDate = (value) =>
  new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  }).format(new Date(value));

const scheduleLabel = (start, end) => {
  if (!start && !end) return "Always on";
  if (start && end) return `${formatScheduleDate(start)} - ${formatScheduleDate(end)}`;
  if (start) return `Starts ${formatScheduleDate(start)}`;
  return `Ends ${formatScheduleDate(end)}`;
};

async function fetchAnnouncement() {
  try {
    fetching.value = true;
    const response = await client(`/api/announcements/${route.params.id}`);
    announcement.value = response?.data || null;
  } catch (error) {
    if (error.response?.status === 404) {
      announcement.value = null;
    } else {
      toast.error("Failed to load announcement");
      announcement.value = null;
    }
  } finally {
    fetching.value = false;
  }
}

onMounted(fetchAnnouncement);
</script>
