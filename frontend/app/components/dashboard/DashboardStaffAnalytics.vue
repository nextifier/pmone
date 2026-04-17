<template>
  <section class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h3 class="page-title text-lg!">Analytics</h3>
      <Select v-if="projects.length > 1" v-model="selectedProjectId">
        <SelectTrigger class="w-[220px]">
          <SelectValue placeholder="Select a project" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="project in projects"
            :key="project.id"
            :value="String(project.id)"
          >
            {{ project.name }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <DashboardChartInquiriesPerDay :data="analytics?.inquiries_per_day" :loading="loading" />
      <DashboardChartInquiriesByStatus
        :data="analytics?.inquiries_by_status"
        :loading="loading"
      />
      <DashboardChartMonthly
        :data="analytics?.visitors_per_month"
        :loading="loading"
        variant="bar"
        data-key="active_users"
        metric-label="Active users"
        color="var(--chart-2)"
        title="Active visitors (last 6 months)"
        description="active users"
      />
      <DashboardChartMonthly
        :data="analytics?.sessions_per_month"
        :loading="loading"
        variant="line"
        data-key="sessions"
        metric-label="Sessions"
        color="var(--chart-3)"
        title="Sessions (last 6 months)"
        description="sessions"
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

const selectedProjectId = ref(
  props.projects[0]?.id != null ? String(props.projects[0].id) : null
);
const analytics = ref(null);
const loading = ref(false);

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
