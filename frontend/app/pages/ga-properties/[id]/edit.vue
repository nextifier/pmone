<template>
  <div class="mx-auto max-w-xl space-y-9 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/ga-properties" />
      <h1 class="page-title">Edit GA4 Property</h1>
    </div>

    <LoadingState v-if="loadingData" label="Loading property.." />

    <FormGaProperty v-else-if="gaProperty" ref="formRef" mode="edit" :ga-property="gaProperty" />

    <div v-else class="py-12 text-center">
      <p class="text-muted-foreground">GA4 property not found</p>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "role"],
  roles: ["master"],
  layout: "app",
});

const route = useRoute();
const id = computed(() => route.params.id);

usePageMeta("", {
  title: `Edit GA4 Property`,
  description: "Edit Google Analytics 4 property",
});

// Data state
const formRef = ref(null);

const {
  data: gaPropertyResponse,
  pending: loadingData,
  error: fetchError,
  refresh: loadGaProperty,
} = await useLazySanctumFetch(() => `/api/google-analytics/ga-properties/${id.value}`, {
  key: `ga-property-edit-${id.value}`,
});

const gaProperty = computed(() => gaPropertyResponse.value?.data || null);

const error = computed(() => {
  if (!fetchError.value) return null;
  if (fetchError.value.statusCode === 404) return "GA4 property not found";
  if (fetchError.value.statusCode === 403) return "You do not have permission";
  return fetchError.value.message || "Failed to load GA property";
});
</script>
