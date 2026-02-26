<template>
  <div class="min-h-screen-offset mx-auto flex max-w-2xl flex-col space-y-6 pt-6 pb-20">
    <div class="flex items-center justify-between gap-2">
      <BackButton destination="/api-consumers" />
    </div>

    <LoadingState v-if="pending" label="Loading API consumer.." />

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
  middleware: ["sanctum:auth", "permission"],
  permissions: ["api_consumers.update"],
  layout: "app",
});

const route = useRoute();
const consumerId = computed(() => route.params.id);

usePageMeta(null, {
  title: "Edit API Consumer",
});

const loading = ref(false);

const {
  data: apiConsumerResponse,
  pending,
  error: fetchError,
} = await useLazySanctumFetch(() => `/api/api-consumers/${consumerId.value}`, {
  key: `api-consumer-edit-${consumerId.value}`,
});

const apiConsumer = computed(() => apiConsumerResponse.value?.data || null);

const error = computed(() => {
  if (!fetchError.value) return null;
  return {
    data: {
      message: fetchError.value.statusCode === 404
        ? "API consumer not found"
        : fetchError.value.statusCode === 403
        ? "You do not have permission"
        : fetchError.value.message || "Failed to load API consumer"
    },
    message: fetchError.value.message || "Failed to load API consumer"
  };
});

const { signalRefresh } = useDataRefresh();

async function handleSuccess() {
  signalRefresh("api-consumers-list");
  await navigateTo("/api-consumers");
}
</script>
