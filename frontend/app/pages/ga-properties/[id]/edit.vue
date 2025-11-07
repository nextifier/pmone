<template>
  <div class="mx-auto max-w-xl space-y-9">
    <div class="flex flex-col items-start gap-y-6">
      <BackButton destination="/ga-properties" />
      <h1 class="page-title">Edit GA4 Property</h1>
    </div>

    <div v-if="loadingData" class="flex justify-center py-12">
      <Spinner class="size-8" />
    </div>

    <FormGaProperty v-else-if="gaProperty" ref="formRef" mode="edit" :ga-property="gaProperty" />

    <div v-else class="py-12 text-center">
      <p class="text-muted-foreground">GA4 property not found</p>
    </div>
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "admin-master"],
  layout: "app",
});

const route = useRoute();
const id = computed(() => route.params.id);

usePageMeta("", {
  title: `Edit GA4 Property`,
  description: "Edit Google Analytics 4 property",
});

const sanctumFetch = useSanctumClient();

// Data state
const gaProperty = ref(null);
const loadingData = ref(true);
const formRef = ref(null);

// Load GA property
async function loadGaProperty() {
  try {
    const response = await sanctumFetch(`/api/google-analytics/ga-properties/${id.value}`);
    gaProperty.value = response.data;
  } catch (err) {
    console.error("Error loading GA property:", err);
    toast.error("Failed to load GA property");
  } finally {
    loadingData.value = false;
  }
}

// Load data on mount
onMounted(async () => {
  await loadGaProperty();
});
</script>
