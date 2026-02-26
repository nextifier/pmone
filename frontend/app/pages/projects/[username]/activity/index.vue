<template>
  <div class="flex flex-col gap-y-4">
    <ActivityFeed
      :activities="activities"
      :meta="meta"
      :loading="loading"
      :per-page="perPage"
      search-placeholder="Search activity..."
      @search="onSearch"
      @page="onPage"
      @per-page-change="onPerPageChange"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Activity · ${props.project?.name || "Project"}`),
});

const route = useRoute();
const client = useSanctumClient();

const activities = ref([]);
const meta = ref(null);
const loading = ref(true);
const search = ref("");
const page = ref(1);
const perPage = ref(20);

async function fetchActivities() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    params.append("page", page.value);
    params.append("per_page", perPage.value);
    if (search.value) params.append("search", search.value);

    const res = await client(
      `/api/projects/${route.params.username}/activity?${params.toString()}`
    );
    activities.value = res.data || [];
    meta.value = res.meta || null;
  } catch (err) {
    console.error("Error loading project activity:", err);
    activities.value = [];
  } finally {
    loading.value = false;
  }
}

function onSearch(query) {
  search.value = query;
  page.value = 1;
  fetchActivities();
}

function onPage(newPage) {
  page.value = newPage;
  fetchActivities();
}

function onPerPageChange(newPerPage) {
  perPage.value = newPerPage;
  page.value = 1;
  fetchActivities();
}

onMounted(() => {
  fetchActivities();
});
</script>
