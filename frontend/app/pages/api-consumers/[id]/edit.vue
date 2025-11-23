<template>
  <div class="min-h-screen-offset mx-auto flex max-w-2xl flex-col space-y-6 pt-6 pb-20">
    <div class="flex items-center justify-between gap-2">
      <BackButton destination="/api-consumers" />
    </div>

    <div v-if="pending" class="flex items-center justify-center py-20">
      <LoadingChaoticOrbit />
    </div>

    <div v-else-if="error" class="py-10">
      <ErrorState
        title="Failed to load API consumer"
        :description="error?.data?.message || error?.message || 'An error occurred'"
      />
    </div>

    <template v-else-if="apiConsumer">
      <div class="space-y-1">
        <h1 class="page-title">Edit API Consumer</h1>
        <p class="page-description">Update settings for {{ apiConsumer.name }}</p>
      </div>

      <ApiConsumerForm
        mode="edit"
        :api-consumer="apiConsumer"
        :loading="loading"
        @update:loading="loading = $event"
        @submit="handleSuccess"
      />
    </template>
  </div>
</template>

<script setup>
import ApiConsumerForm from "@/components/api-consumer/Form.vue";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const route = useRoute();
const consumerId = computed(() => route.params.id);

usePageMeta("api-consumers", {
  title: "Edit API Consumer",
});

const loading = ref(false);
const pending = ref(false);
const error = ref(null);
const apiConsumer = ref(null);

// Fetch API consumer data
const fetchApiConsumer = async () => {
  try {
    pending.value = true;
    error.value = null;
    const client = useSanctumClient();
    const response = await client(`/api/api-consumers/${consumerId.value}`);
    apiConsumer.value = response.data;
  } catch (err) {
    error.value = err;
    console.error("Failed to fetch API consumer:", err);
  } finally {
    pending.value = false;
  }
};

await fetchApiConsumer();

async function handleSuccess() {
  const needsRefresh = useState("api-consumers-needs-refresh", () => false);
  needsRefresh.value = true;
  await navigateTo("/api-consumers");
}
</script>
