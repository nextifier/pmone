<template>
  <div class="mx-auto flex flex-col gap-y-6 pt-4 pb-16 lg:max-w-5xl xl:max-w-6xl">
    <!-- Header -->
    <div class="flex flex-col gap-y-1">
      <h2 class="page-title">
        <DashboardGreeting />
      </h2>
      <p class="page-description">Here's what's happening with your profile</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
      <DashboardStatsCard
        title="Visits"
        description="Profile page views"
        :value="stats?.visits?.current ?? 0"
        :change="stats?.visits?.change"
        :trend="stats?.visits?.trend"
        icon="hugeicons:view"
        icon-color="text-emerald-600 dark:text-emerald-400"
        :loading="loading"
      />
      <DashboardStatsCard
        title="Clicks"
        description="Link clicks on your profile"
        :value="stats?.clicks?.current ?? 0"
        :change="stats?.clicks?.change"
        :trend="stats?.clicks?.trend"
        icon="hugeicons:cursor-pointer-02"
        icon-color="text-violet-600 dark:text-violet-400"
        :loading="loading"
      />
      <DashboardStatsCard
        v-if="canViewInbox"
        title="Inbox"
        description="Unread messages"
        :value="stats?.inbox?.unread ?? 0"
        icon="hugeicons:mail-open-love"
        icon-color="text-rose-600 dark:text-rose-400"
        href="/inbox"
        :loading="loading"
      />
      <DashboardStatsCard
        v-else
        title="Links"
        description="Your short links"
        :value="stats?.links?.total ?? 0"
        icon="hugeicons:link-02"
        icon-color="text-sky-600 dark:text-sky-400"
        href="/links"
        :loading="loading"
      />
      <DashboardStatsCard
        v-if="canViewInbox"
        title="Active Links"
        description="Your short links"
        :value="stats?.links?.total ?? 0"
        icon="hugeicons:link-02"
        icon-color="text-sky-600 dark:text-sky-400"
        href="/links"
        :loading="loading"
      />
    </div>

    <!-- Chart Section -->
    <div class="frame">
      <div class="frame-header">
        <h2 class="text-foreground text-base font-medium tracking-tight">Visits Over Time</h2>
        <p class="text-muted-foreground text-sm tracking-tight">Last 14 days activity</p>
      </div>
      <div class="frame-panel px-0!">
        <template v-if="loading">
          <div class="flex h-[40vh] w-full items-center justify-center">
            <Spinner class="size-8" />
          </div>
        </template>
        <template v-else-if="!chartData || chartData.length === 0">
          <div class="flex h-[40vh] w-full flex-col items-center justify-center gap-2">
            <div class="bg-muted flex size-12 items-center justify-center rounded-full">
              <Icon name="hugeicons:chart-line-data-01" class="text-muted-foreground size-6" />
            </div>
            <p class="text-muted-foreground text-sm">No visit data yet</p>
          </div>
        </template>
        <template v-else>
          <ClientOnly>
            <ChartLine
              :data="chartData"
              :config="chartConfig"
              :gradient="true"
              data-key="count"
              class="h-auto!"
            />
          </ClientOnly>
        </template>
      </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid gap-4 lg:grid-cols-2">
      <!-- Recent Activity -->
      <DashboardRecentActivity :visits="recentVisits" :clicks="recentClicks" :loading="loading" />

      <!-- Top Links -->
      <DashboardTopLinks :links="topLinks" :loading="loading" />
    </div>

    <!-- Quick Actions -->
    <section class="flex flex-col gap-y-4">
      <div class="flex items-center gap-x-2">
        <Icon name="hugeicons:flash" class="text-primary size-5" />
        <h3 class="text-foreground text-base font-semibold tracking-tight">Quick Actions</h3>
      </div>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <NuxtLink
          v-if="canCreatePost"
          to="/posts/create"
          class="group bg-card hover:border-primary/50 flex flex-col items-center gap-y-3 rounded-xl border p-4 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="bg-primary/10 group-hover:bg-primary/20 flex size-12 items-center justify-center rounded-xl transition-colors"
          >
            <Icon name="hugeicons:quill-write-02" class="text-primary size-6" />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">New Post</span>
        </NuxtLink>

        <NuxtLink
          v-if="canCreateLink"
          to="/links/create"
          class="group bg-card flex flex-col items-center gap-y-3 rounded-xl border p-4 transition-all hover:border-violet-500/50 hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-12 items-center justify-center rounded-xl bg-violet-500/10 transition-colors group-hover:bg-violet-500/20"
          >
            <Icon name="hugeicons:link-02" class="size-6 text-violet-600 dark:text-violet-400" />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">New Link</span>
        </NuxtLink>

        <NuxtLink
          v-if="canCreateProject"
          to="/projects/create"
          class="group bg-card flex flex-col items-center gap-y-3 rounded-xl border p-4 transition-all hover:border-emerald-500/50 hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-12 items-center justify-center rounded-xl bg-emerald-500/10 transition-colors group-hover:bg-emerald-500/20"
          >
            <Icon
              name="hugeicons:folder-add"
              class="size-6 text-emerald-600 dark:text-emerald-400"
            />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">New Project</span>
        </NuxtLink>

        <NuxtLink
          to="/qr"
          class="group bg-card flex flex-col items-center gap-y-3 rounded-xl border p-4 transition-all hover:border-amber-500/50 hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-12 items-center justify-center rounded-xl bg-amber-500/10 transition-colors group-hover:bg-amber-500/20"
          >
            <Icon name="hugeicons:qr-code" class="size-6 text-amber-600 dark:text-amber-400" />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">QR Code</span>
        </NuxtLink>
      </div>
    </section>

    <!-- Browse Features -->
    <section class="flex flex-col gap-y-4">
      <div class="flex items-center gap-x-2">
        <Icon name="hugeicons:dashboard-square-02" class="text-muted-foreground size-5" />
        <h3 class="text-foreground text-base font-semibold tracking-tight">Browse</h3>
      </div>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <!-- Inbox -->
        <NuxtLink
          v-if="canViewInbox"
          to="/inbox"
          class="group bg-card hover:bg-muted/50 flex items-start gap-x-4 rounded-xl border p-4 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 transition-colors group-hover:bg-rose-500/20"
          >
            <Icon name="hugeicons:mail-open-love" class="size-5 text-rose-600 dark:text-rose-400" />
          </div>
          <div class="flex flex-col gap-y-0.5">
            <span class="text-foreground text-sm font-semibold tracking-tight">Inbox</span>
            <span class="text-muted-foreground text-xs tracking-tight"
              >View contact form submissions</span
            >
          </div>
        </NuxtLink>

        <!-- Posts -->
        <NuxtLink
          v-if="canViewPosts"
          to="/posts"
          class="group bg-card hover:bg-muted/50 flex items-start gap-x-4 rounded-xl border p-4 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="bg-primary/10 group-hover:bg-primary/20 flex size-11 shrink-0 items-center justify-center rounded-lg transition-colors"
          >
            <Icon name="hugeicons:task-edit-01" class="text-primary size-5" />
          </div>
          <div class="flex flex-col gap-y-0.5">
            <span class="text-foreground text-sm font-semibold tracking-tight">Posts</span>
            <span class="text-muted-foreground text-xs tracking-tight"
              >Manage your blog content</span
            >
          </div>
        </NuxtLink>

        <!-- Short Links -->
        <NuxtLink
          v-if="canViewLinks"
          to="/links"
          class="group bg-card hover:bg-muted/50 flex items-start gap-x-4 rounded-xl border p-4 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 transition-colors group-hover:bg-violet-500/20"
          >
            <Icon name="hugeicons:unlink-02" class="size-5 text-violet-600 dark:text-violet-400" />
          </div>
          <div class="flex flex-col gap-y-0.5">
            <span class="text-foreground text-sm font-semibold tracking-tight"
              >Short Links & QR</span
            >
            <span class="text-muted-foreground text-xs tracking-tight"
              >Manage short URLs & dynamic QR</span
            >
          </div>
        </NuxtLink>

        <!-- Projects -->
        <NuxtLink
          v-if="canViewProjects"
          to="/projects"
          class="group bg-card hover:bg-muted/50 flex items-start gap-x-4 rounded-xl border p-4 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 transition-colors group-hover:bg-emerald-500/20"
          >
            <Icon
              name="hugeicons:layers-01"
              class="size-5 text-emerald-600 dark:text-emerald-400"
            />
          </div>
          <div class="flex flex-col gap-y-0.5">
            <span class="text-foreground text-sm font-semibold tracking-tight">Projects</span>
            <span class="text-muted-foreground text-xs tracking-tight"
              >Manage portfolios & projects</span
            >
          </div>
        </NuxtLink>

        <!-- Web Analytics -->
        <NuxtLink
          v-if="canViewAnalytics"
          to="/web-analytics"
          class="group bg-card hover:bg-muted/50 flex items-start gap-x-4 rounded-xl border p-4 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 transition-colors group-hover:bg-sky-500/20"
          >
            <Icon
              name="hugeicons:analysis-text-link"
              class="size-5 text-sky-600 dark:text-sky-400"
            />
          </div>
          <div class="flex flex-col gap-y-0.5">
            <span class="text-foreground text-sm font-semibold tracking-tight">Web Analytics</span>
            <span class="text-muted-foreground text-xs tracking-tight"
              >View traffic & visitor stats</span
            >
          </div>
        </NuxtLink>

        <!-- API Consumers -->
        <NuxtLink
          v-if="canViewApiConsumers"
          to="/api-consumers"
          class="group bg-card hover:bg-muted/50 flex items-start gap-x-4 rounded-xl border p-4 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 transition-colors group-hover:bg-indigo-500/20"
          >
            <Icon name="hugeicons:api" class="size-5 text-indigo-600 dark:text-indigo-400" />
          </div>
          <div class="flex flex-col gap-y-0.5">
            <span class="text-foreground text-sm font-semibold tracking-tight">API Consumers</span>
            <span class="text-muted-foreground text-xs tracking-tight"
              >Manage external API access</span
            >
          </div>
        </NuxtLink>
      </div>
    </section>

    <!-- Admin Section -->
    <section v-if="showAdminSection" class="flex flex-col gap-y-4">
      <div class="flex items-center gap-x-2">
        <Icon name="hugeicons:setting-06" class="text-muted-foreground size-5" />
        <h3 class="text-foreground text-base font-semibold tracking-tight">Administration</h3>
      </div>

      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Users -->
        <NuxtLink
          v-if="canViewUsers"
          to="/users"
          class="group bg-card hover:bg-muted/50 flex items-center gap-x-3 rounded-xl border p-3 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-500/10 transition-colors group-hover:bg-slate-500/20"
          >
            <Icon name="hugeicons:user-group" class="size-4.5 text-slate-600 dark:text-slate-400" />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">Users</span>
        </NuxtLink>

        <!-- Roles -->
        <NuxtLink
          v-if="canViewRoles"
          to="/roles"
          class="group bg-card hover:bg-muted/50 flex items-center gap-x-3 rounded-xl border p-3 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-cyan-500/10 transition-colors group-hover:bg-cyan-500/20"
          >
            <Icon
              name="hugeicons:user-settings-01"
              class="size-4.5 text-cyan-600 dark:text-cyan-400"
            />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">Roles</span>
        </NuxtLink>

        <!-- Permissions -->
        <NuxtLink
          v-if="canViewPermissions"
          to="/permissions"
          class="group bg-card hover:bg-muted/50 flex items-center gap-x-3 rounded-xl border p-3 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-orange-500/10 transition-colors group-hover:bg-orange-500/20"
          >
            <Icon
              name="hugeicons:shield-key"
              class="size-4.5 text-orange-600 dark:text-orange-400"
            />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">Permissions</span>
        </NuxtLink>

        <!-- GA Properties -->
        <NuxtLink
          v-if="isMaster"
          to="/ga-properties"
          class="group bg-card hover:bg-muted/50 flex items-center gap-x-3 rounded-xl border p-3 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-green-500/10 transition-colors group-hover:bg-green-500/20"
          >
            <Icon
              name="hugeicons:analytics-01"
              class="size-4.5 text-green-600 dark:text-green-400"
            />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">GA Properties</span>
        </NuxtLink>

        <!-- Activity Logs -->
        <NuxtLink
          v-if="canViewLogs"
          to="/logs"
          class="group bg-card hover:bg-muted/50 flex items-center gap-x-3 rounded-xl border p-3 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-pink-500/10 transition-colors group-hover:bg-pink-500/20"
          >
            <Icon name="hugeicons:activity-03" class="size-4.5 text-pink-600 dark:text-pink-400" />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">Activity Logs</span>
        </NuxtLink>

        <!-- Settings -->
        <NuxtLink
          to="/settings/profile"
          class="group bg-card hover:bg-muted/50 flex items-center gap-x-3 rounded-xl border p-3 transition-all hover:shadow-sm active:scale-98"
        >
          <div
            class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-500/10 transition-colors group-hover:bg-gray-500/20"
          >
            <Icon name="hugeicons:settings-01" class="size-4.5 text-gray-600 dark:text-gray-400" />
          </div>
          <span class="text-foreground text-sm font-medium tracking-tight">Settings</span>
        </NuxtLink>
      </div>
    </section>
  </div>
</template>

<script setup>
const { hasPermission, hasRole } = usePermission();
const client = useSanctumClient();

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta("dashboard");

// Permission checks for Quick Actions
const canCreatePost = computed(() => hasPermission("posts.create"));
const canCreateLink = computed(() => hasPermission("short_links.create"));
const canCreateProject = computed(() => hasPermission("projects.create"));

// Permission checks for Browse section
const canViewInbox = computed(() => hasPermission("contact_forms.read"));
const canViewPosts = computed(() => hasPermission("posts.read"));
const canViewLinks = computed(() => hasPermission("short_links.read"));
const canViewProjects = computed(() => hasPermission("projects.read"));
const canViewAnalytics = computed(() => hasPermission("analytics.view"));
const canViewApiConsumers = computed(() => hasPermission("api_consumers.read"));

// Permission checks for Admin section
const canViewUsers = computed(() => hasPermission("users.read"));
const canViewRoles = computed(() => hasPermission("roles.read"));
const canViewPermissions = computed(() => hasPermission("permissions.read"));
const canViewLogs = computed(() => hasPermission("admin.logs"));
const isMaster = computed(() => hasRole("master"));

// Show admin section if user has any admin permission
const showAdminSection = computed(() => {
  return (
    canViewUsers.value ||
    canViewRoles.value ||
    canViewPermissions.value ||
    canViewLogs.value ||
    isMaster.value
  );
});

// Dashboard data
const loading = ref(true);
const stats = ref(null);
const visitsChartRaw = ref([]);
const recentVisits = ref([]);
const recentClicks = ref([]);
const topLinks = ref([]);

// Chart data transformation for ChartLine component
const chartData = computed(() => {
  if (!visitsChartRaw.value || visitsChartRaw.value.length === 0) return [];
  return visitsChartRaw.value.map((item) => ({
    date: new Date(item.date),
    count: item.count || 0,
  }));
});

// Chart config for ChartLine component
const chartConfig = {
  count: {
    label: "Visits",
    color: "var(--chart-1)",
  },
};

// Fetch dashboard stats
const fetchDashboardStats = async () => {
  try {
    loading.value = true;
    const response = await client("/api/dashboard/stats");

    if (response?.data) {
      stats.value = response.data.stats;
      visitsChartRaw.value = response.data.visits_chart || [];
      recentVisits.value = response.data.recent_visits || [];
      recentClicks.value = response.data.recent_clicks || [];
      topLinks.value = response.data.top_links || [];
    }
  } catch (error) {
    console.error("Failed to fetch dashboard stats:", error);
  } finally {
    loading.value = false;
  }
};

// Fetch stats on mount
onMounted(() => {
  fetchDashboardStats();
});
</script>
