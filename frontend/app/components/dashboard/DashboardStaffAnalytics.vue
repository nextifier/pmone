<template>
  <section class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h3 class="page-title text-lg!">Analytics</h3>
      <Select v-if="projects.length > 1" v-model="selectedProjectId">
        <SelectTrigger class="w-[220px]">
          <SelectValue placeholder="Select a project" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="project in projects" :key="project.id" :value="String(project.id)">
            {{ project.name }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="grid grid-cols-1 gap-2.5 lg:grid-cols-3">
      <DashboardChartInquiriesPerDay :data="analytics?.inquiries_per_day" :loading="loading" />
      <DashboardChartInquiriesByProject
        :data="analytics?.inquiries_by_project"
        :loading="loading"
      />
      <DashboardChartMonthly
        :data="analytics?.visitors_per_month"
        :loading="loading"
        variant="horizontal-bar"
        data-key="active_users"
        metric-label="Active users"
        color="var(--chart-1)"
        title="Active visitors (last 6 months)"
        description="active users"
      />
    </div>
  </section>
</template>

<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const props = defineProps({
  projects: {
    type: Array,
    required: true,
  },
});

const client = useSanctumClient();

const STORAGE_KEY = "dashboard_analytics_project_id";

const getInitialProjectId = () => {
  const fallback = props.projects[0]?.id != null ? String(props.projects[0].id) : null;

  if (typeof window === "undefined") {
    return fallback;
  }

  const stored = localStorage.getItem(STORAGE_KEY);
  const isValid = stored && props.projects.some((p) => String(p.id) === stored);

  return isValid ? stored : fallback;
};

const selectedProjectId = ref(getInitialProjectId());
const analytics = ref(null);
const loading = ref(false);

watch(selectedProjectId, (id) => {
  if (typeof window !== "undefined" && id) {
    localStorage.setItem(STORAGE_KEY, id);
  }
});

const fetchAnalytics = async (projectId) => {
  if (!projectId) {
    analytics.value = null;
    return;
  }

  try {
    loading.value = true;
    const response = await client("/api/dashboard/staff-analytics", {
      query: { project_id: projectId },
    });
    analytics.value = response?.data ?? null;
  } catch (error) {
    console.error("Failed to fetch staff analytics:", error);
    analytics.value = null;
  } finally {
    loading.value = false;
  }
};

watch(selectedProjectId, (id) => fetchAnalytics(id), { immediate: true });
</script>
