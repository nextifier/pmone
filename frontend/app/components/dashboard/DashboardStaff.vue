<template>
  <div
    class="mx-auto flex flex-col gap-y-8 pt-2 pt-4 pb-16 pb-20 lg:max-w-4xl lg:gap-y-10 lg:pt-4 xl:max-w-6xl"
  >
    <DashboardGreetingTips :tip-definitions="tipDefinitions" :tips="tips" :loading="loading" />

    <!-- All Events -->
    <DashboardAllEvents :events="allEvents" :loading="loading" />

    <!-- My Projects -->
    <DashboardMyProjects :projects="myProjects" :loading="loading" />
  </div>
</template>

<script setup>
const props = defineProps({
  tipDefinitions: { type: Array, required: true },
});

const client = useSanctumClient();

const loading = ref(true);
const tips = ref(null);
const allEvents = ref([]);
const myProjects = ref([]);

const fetchData = async () => {
  try {
    loading.value = true;
    const response = await client("/api/dashboard/stats");

    if (response?.data) {
      tips.value = response.data.tips || null;
      allEvents.value = response.data.all_events || [];
      myProjects.value = response.data.my_projects || [];
    }
  } catch (error) {
    console.error("Failed to fetch dashboard stats:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchData);
</script>
