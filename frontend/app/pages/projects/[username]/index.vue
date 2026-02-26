<template>
  <div
    class="mx-auto flex flex-col gap-y-8 pb-16 lg:max-w-4xl lg:gap-y-10 xl:max-w-6xl"
  >
    <!-- Events -->
    <DashboardAllEvents :events="events" :loading="eventsLoading" />

    <!-- Inquiries -->
    <section v-if="!inquiriesLoading || inquiries.length" class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="page-title">Recent Inquiries</h3>
        <NuxtLink
          :to="`${base}/inquiries`"
          class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
        >
          View all
        </NuxtLink>
      </div>

      <template v-if="inquiriesLoading">
        <div class="divide-border divide-y border-y">
          <div v-for="i in 3" :key="i" class="flex items-center gap-x-3 py-3">
            <Skeleton class="size-2 shrink-0 rounded-full" />
            <div class="flex flex-1 flex-col gap-y-1">
              <Skeleton class="h-3.5 w-32" />
              <Skeleton class="h-3 w-48" />
            </div>
            <Skeleton class="h-3 w-16" />
          </div>
        </div>
      </template>

      <div v-else-if="inquiries.length" class="divide-border divide-y border-y">
        <NuxtLink
          v-for="item in inquiries"
          :key="item.id"
          :to="`${base}/inquiries`"
          class="hover:bg-muted/50 flex items-center gap-x-3 py-3 transition"
        >
          <div
            :class="[
              'size-2 shrink-0 rounded-full',
              item.status === 'new'
                ? 'bg-blue-500'
                : item.status === 'in_progress'
                  ? 'bg-amber-500'
                  : item.status === 'completed'
                    ? 'bg-green-500'
                    : 'bg-zinc-400',
            ]"
          />
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium tracking-tight">
              {{ item.form_data_preview?.name || "Unknown" }}
            </p>
            <p class="text-muted-foreground truncate text-xs tracking-tight">
              {{ item.subject || "No subject" }}
            </p>
          </div>
          <span class="text-muted-foreground shrink-0 text-xs tracking-tight">
            {{ $dayjs(item.created_at).fromNow() }}
          </span>
        </NuxtLink>
      </div>

      <div v-else class="flex items-center gap-2 py-4">
        <Icon name="hugeicons:mail-01" class="text-muted-foreground size-4" />
        <p class="text-muted-foreground text-sm tracking-tight">No inquiries yet</p>
      </div>
    </section>

    <!-- Members -->
    <section v-if="members.length" class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="page-title">Members</h3>
        <NuxtLink
          :to="`${base}/members`"
          class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
        >
          Manage
        </NuxtLink>
      </div>

      <div class="flex flex-wrap gap-3">
        <NuxtLink
          v-for="member in members"
          :key="member.id"
          :to="`/${member.username}`"
          class="hover:bg-muted/50 flex items-center gap-x-2.5 rounded-lg border px-3 py-2 transition"
        >
          <Avatar :model="member" class="size-7 shrink-0" rounded="rounded-full" />
          <span class="text-sm tracking-tight">{{ member.name }}</span>
        </NuxtLink>
      </div>
    </section>

    <!-- Recent Activity -->
    <section v-if="!activityLoading || recentActivity.length" class="space-y-4">
      <div class="flex items-center justify-between">
        <h3 class="page-title">Recent Activity</h3>
        <NuxtLink
          :to="`${base}/activity`"
          class="text-muted-foreground hover:text-foreground text-sm tracking-tight transition"
        >
          View all
        </NuxtLink>
      </div>

      <template v-if="activityLoading">
        <div class="flex flex-col gap-y-3">
          <div v-for="i in 4" :key="i" class="flex items-center gap-x-3">
            <Skeleton class="size-6 shrink-0 rounded-full" />
            <div class="flex flex-1 flex-col gap-y-1">
              <Skeleton class="h-3.5 w-48" />
              <Skeleton class="h-3 w-20" />
            </div>
          </div>
        </div>
      </template>

      <div v-else-if="recentActivity.length" class="flex flex-col">
        <div
          v-for="(activity, index) in recentActivity"
          :key="activity.id"
          class="relative flex gap-x-3 pb-4 last:pb-0"
        >
          <div
            v-if="index < recentActivity.length - 1"
            class="bg-border absolute top-7 bottom-0 left-[11px] w-px"
          />
          <div
            :class="[
              'mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border',
              activityColorClasses[activity.color] || activityColorClasses.zinc,
            ]"
          >
            <Icon :name="activity.icon" class="size-3" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-foreground truncate text-sm tracking-tight">
              {{ activity.human_description }}
            </p>
            <span class="text-muted-foreground text-xs tracking-tight">
              {{ $dayjs(activity.created_at).fromNow() }}
            </span>
          </div>
        </div>
      </div>

      <div v-else class="flex items-center gap-2 py-4">
        <Icon name="hugeicons:activity-01" class="text-muted-foreground size-4" />
        <p class="text-muted-foreground text-sm tracking-tight">No activity yet</p>
      </div>
    </section>
  </div>
</template>

<script setup>
const props = defineProps({
  project: Object,
});

const route = useRoute();
const { $dayjs } = useNuxtApp();
const client = useSanctumClient();

usePageMeta(null, { title: computed(() => `Overview · ${props.project?.name || ""}`) });

const base = computed(() => `/projects/${route.params.username}`);

// Members from project prop
const members = computed(() => props.project?.members || []);

// Events
const events = ref([]);
const eventsLoading = ref(true);

// Inquiries
const inquiries = ref([]);
const inquiriesLoading = ref(true);

// Activity
const recentActivity = ref([]);
const activityLoading = ref(true);

const activityColorClasses = {
  green: "border-green-200 bg-green-50 text-green-600 dark:border-green-800 dark:bg-green-950 dark:text-green-400",
  blue: "border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400",
  red: "border-red-200 bg-red-50 text-red-600 dark:border-red-800 dark:bg-red-950 dark:text-red-400",
  amber: "border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400",
  purple: "border-purple-200 bg-purple-50 text-purple-600 dark:border-purple-800 dark:bg-purple-950 dark:text-purple-400",
  zinc: "border-border bg-muted text-muted-foreground",
};

async function fetchEvents() {
  eventsLoading.value = true;
  try {
    const res = await client(
      `/api/projects/${route.params.username}/events?per_page=5&sort=-created_at`
    );
    events.value = res.data || [];
  } catch {
    events.value = [];
  } finally {
    eventsLoading.value = false;
  }
}

async function fetchInquiries() {
  if (!props.project?.id) {
    inquiriesLoading.value = false;
    return;
  }
  inquiriesLoading.value = true;
  try {
    const res = await client(
      `/api/contact-form-submissions?filter_project=${props.project.id}&per_page=5&sort=-created_at`
    );
    inquiries.value = res.data || [];
  } catch {
    inquiries.value = [];
  } finally {
    inquiriesLoading.value = false;
  }
}

async function fetchActivity() {
  activityLoading.value = true;
  try {
    const res = await client(
      `/api/projects/${route.params.username}/activity?per_page=5`
    );
    recentActivity.value = res.data || [];
  } catch {
    recentActivity.value = [];
  } finally {
    activityLoading.value = false;
  }
}

onMounted(() => {
  fetchEvents();
  fetchInquiries();
  fetchActivity();
});

// Re-fetch inquiries when project becomes available
watch(
  () => props.project?.id,
  (newId) => {
    if (newId) {
      if (!inquiries.value.length && !inquiriesLoading.value) fetchInquiries();
    }
  }
);
</script>
