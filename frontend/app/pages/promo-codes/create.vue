<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-5 pt-4 pb-16">
    <div class="flex flex-col items-start gap-y-5">
      <ButtonBack destination="/promo-codes" force-destination />

      <div class="flex flex-col">
        <h1 class="page-title">Create Promo Code</h1>
        <p class="page-description mt-1.5">Issue a new code attached to an existing rule.</p>
      </div>
    </div>

    <FormPromoCode
      :is-create="true"
      :loading="loading"
      :errors="errors"
      submit-text="Create Code"
      submit-loading-text="Creating.."
      @submit="handleCreate"
    />
  </div>
</template>

<script setup>
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["promo_codes.create"],
  layout: "app",
});

usePageMeta(null, { title: "Create Promo Code" });

const client = useSanctumClient();
const loading = ref(false);
const errors = ref({});

async function handleCreate(payload) {
  if (!payload.rule_ulid) {
    errors.value = { promotion_rule_id: ["Please select a promotion rule."] };
    return;
  }
  loading.value = true;
  errors.value = {};
  try {
    const ruleUlid = payload.rule_ulid;
    const body = { ...payload };
    delete body.rule_ulid;

    await client(`/api/promotion-rules/${ruleUlid}/codes`, { method: "POST", body });
    toast.success("Promo code created");
    await navigateTo("/promo-codes");
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response._data?.errors || {};
    } else {
      toast.error(error.response?._data?.message || error.message || "Failed to create code");
    }
  } finally {
    loading.value = false;
  }
}
</script>
