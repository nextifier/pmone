<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex items-center gap-x-2.5">
      <NuxtLink to="/hotels" class="hover:bg-muted text-muted-foreground inline-flex size-8 items-center justify-center rounded-md">
        <Icon name="lucide:arrow-left" class="size-4" />
      </NuxtLink>
      <h1 class="page-title">Add Hotel</h1>
    </div>

    <HotelForm :saving="saving" submit-label="Create Hotel" @submit="handleSubmit" @cancel="navigateTo('/hotels')" />
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

usePageMeta(null, {
  title: "Add Hotel · Hotels",
});

const client = useSanctumClient();
const saving = ref(false);

const handleSubmit = async (payload) => {
  saving.value = true;
  try {
    const response = await client("/api/hotels", {
      method: "POST",
      body: payload,
    });
    toast.success("Hotel created");
    await navigateTo(`/hotels/${response.data.slug}`);
  } catch (err) {
    toast.error("Failed to create hotel", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
};
</script>
