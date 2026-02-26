<template>
  <div class="min-h-screen-offset mx-auto flex max-w-2xl flex-col space-y-6 pt-6 pb-20">
    <div class="flex items-center justify-between gap-2">
      <BackButton destination="/api-consumers" />
    </div>

    <div class="space-y-1">
      <h1 class="page-title">Create API Consumer</h1>
      <p class="page-description">
        Create a new API consumer to access your application data from external websites.
      </p>
    </div>

    <ApiConsumerForm
      mode="create"
      :loading="loading"
      @update:loading="loading = $event"
      @submit="handleSuccess"
    />
  </div>
</template>

<script setup>
import ApiConsumerForm from "@/components/api-consumer/Form.vue";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["api_consumers.create"],
  layout: "app",
});

usePageMeta(null, {
  title: "Create API Consumer",
});

const loading = ref(false);

const { signalRefresh } = useDataRefresh();

async function handleSuccess() {
  signalRefresh("api-consumers-list");
  await navigateTo("/api-consumers");
}
</script>
