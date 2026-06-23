<template>
  <div class="flex flex-col gap-y-4">
    <ActivityFeed
      :activities="activities"
      :meta="meta"
      :loading="loading"
      :per-page="perPage"
      :show-search="false"
      @page="onPage"
      @per-page-change="onPerPageChange"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  user: { type: Object, required: true },
});

usePageMeta(null, {
  title: computed(() => `${props.user?.name || "User"} · Login History`),
});

const client = useSanctumClient();

const activities = ref([]);
const meta = ref(null);
const loading = ref(true);
const page = ref(1);
const perPage = ref(20);

async function fetchHistory() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    params.append("page", page.value);
    params.append("per_page", perPage.value);
    const res = await client(`/api/users/${props.user.username}/login-history?${params.toString()}`);
    activities.value = res.data || [];
    meta.value = res.meta || null;
  } catch (err) {
    console.error("Error loading login history:", err);
    activities.value = [];
  } finally {
    loading.value = false;
  }
}

function onPage(newPage) {
  page.value = newPage;
  fetchHistory();
}

function onPerPageChange(newPerPage) {
  perPage.value = newPerPage;
  page.value = 1;
  fetchHistory();
}

onMounted(fetchHistory);
</script>
