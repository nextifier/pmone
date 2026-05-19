<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-5 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-5">
      <ButtonBack destination="/promotion-rules" />

      <div class="flex flex-col">
        <h1 class="page-title">Edit Promotion Rule</h1>
        <p v-if="initialData?.name" class="page-description mt-1.5">{{ initialData.name }}</p>
      </div>
    </div>

    <div v-if="fetching" class="space-y-4">
      <Skeleton class="h-32 w-full rounded-md" />
      <Skeleton class="h-32 w-full rounded-md" />
    </div>

    <FormPromotionRule
      v-else-if="initialData"
      :initial-data="initialData"
      :loading="loading"
      :errors="errors"
      submit-text="Save Rule"
      submit-loading-text="Saving.."
      @submit="handleUpdate"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promotion_rules.update"],
  layout: "app",
});

usePageMeta(null, { title: "Edit Promotion Rule" });

const route = useRoute();
const client = useSanctumClient();
const loading = ref(false);
const fetching = ref(true);
const errors = ref({});
const initialData = ref(null);

async function fetchRule() {
  try {
    fetching.value = true;
    const response = await client(`/api/promotion-rules/${route.params.ulid}`);
    initialData.value = response?.data;
  } catch (error) {
    toast.error("Failed to load rule");
    await navigateTo("/promotion-rules");
  } finally {
    fetching.value = false;
  }
}

async function handleUpdate(payload) {
  loading.value = true;
  errors.value = {};
  try {
    await client(`/api/promotion-rules/${route.params.ulid}`, { method: "PATCH", body: payload });
    toast.success("Rule updated");
    await navigateTo("/promotion-rules");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to update rule");
    }
  } finally {
    loading.value = false;
  }
}

onMounted(fetchRule);
</script>
