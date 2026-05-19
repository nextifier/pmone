<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack destination="/hotels-master" />
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:hotel-01" class="size-5 sm:size-6" />
        <h1 class="page-title">Add Hotel</h1>
      </div>
      <p class="text-muted-foreground text-sm tracking-tight">
        Create a global hotel record. You can attach it to one or more events afterwards.
      </p>
    </div>

    <HotelForm
      :saving="saving"
      :errors="errors"
      submit-label="Create Hotel"
      @submit="handleSubmit"
      @cancel="navigateTo('/hotels-master')"
    />
  </div>
</template>

<script setup>
import HotelForm from "@/components/hotel/HotelForm.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.create"],
  layout: "app",
});

usePageMeta(null, { title: "Add Hotel · Master" });

const client = useSanctumClient();
const saving = ref(false);
const errors = ref({});

const handleSubmit = async (payload) => {
  saving.value = true;
  errors.value = {};
  try {
    const response = await client("/api/hotels", {
      method: "POST",
      body: payload,
    });
    toast.success("Hotel created");
    await navigateTo(`/hotels-master/${response.data.slug}`);
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Failed to create hotel", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
};
</script>
