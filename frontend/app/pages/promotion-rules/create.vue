<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-5 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-5">
      <ButtonBack destination="/promotion-rules" />

      <div class="flex flex-col">
        <h1 class="page-title">Create Promotion Rule</h1>
        <p class="page-description mt-1.5">
          Define a new discount or penalty rule. Codes can be issued from this rule afterwards.
        </p>
      </div>
    </div>

    <FormPromotionRule
      :is-create="true"
      :loading="loading"
      :errors="errors"
      submit-text="Create Rule"
      submit-loading-text="Creating.."
      @submit="handleCreate"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promotion_rules.create"],
  layout: "app",
});

usePageMeta(null, { title: "Create Promotion Rule" });

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});

async function handleCreate(payload) {
  loading.value = true;
  errors.value = {};
  try {
    await client("/api/promotion-rules", { method: "POST", body: payload });
    toast.success("Promotion rule created");
    await navigateTo("/promotion-rules");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || "Failed to create rule");
    }
  } finally {
    loading.value = false;
  }
}
</script>
