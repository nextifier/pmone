<template>
  <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div v-for="card in cards" :key="card.label" class="rounded-lg border p-3.5">
      <div class="text-muted-foreground flex items-center gap-x-1.5 text-sm tracking-tight">
        <Icon :name="card.icon" class="size-4 shrink-0" />
        <span>{{ card.label }}</span>
      </div>
      <div class="mt-1.5 flex items-end gap-x-1.5">
        <span class="text-xl font-semibold tracking-tighter">
          <template v-if="loading">-</template>
          <template v-else>{{ card.value }}</template>
        </span>
        <span v-if="card.suffix && !loading" class="text-muted-foreground pb-0.5 text-sm tracking-tight">
          {{ card.suffix }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
const client = useSanctumClient();

const stats = ref(null);
const loading = ref(true);

const cards = computed(() => [
  { label: "Total users", icon: "hugeicons:user-group", value: stats.value?.total ?? 0 },
  { label: "Online now", icon: "hugeicons:radar-01", value: stats.value?.online_now ?? 0 },
  {
    label: "Verified",
    icon: "material-symbols:verified",
    value: `${stats.value?.verified_percent ?? 0}%`,
    suffix: stats.value ? `${stats.value.verified} users` : null,
  },
  { label: "New this week", icon: "hugeicons:user-add-01", value: stats.value?.new_this_week ?? 0 },
]);

onMounted(async () => {
  try {
    const res = await client("/api/users/stats");
    stats.value = res.data || null;
  } catch (err) {
    console.error("Error loading user stats:", err);
  } finally {
    loading.value = false;
  }
});
</script>
