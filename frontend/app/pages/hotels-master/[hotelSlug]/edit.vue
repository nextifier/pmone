<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <div class="flex flex-col items-start gap-y-4">
      <ButtonBack :destination="`/hotels-master/${hotelSlug}`" force-destination />
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:edit-02" class="size-5 sm:size-6" />
        <h1 class="page-title">Edit Hotel</h1>
      </div>
      <p class="text-muted-foreground text-sm tracking-tight">
        Changes apply globally to every event that has this hotel attached.
      </p>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <HotelForm
      v-else-if="hotel"
      :initial="hotel"
      :saving="saving"
      :errors="errors"
      submit-label="Save Changes"
      @submit="handleSubmit"
      @cancel="navigateTo(`/hotels-master/${hotelSlug}`)"
    />
  </div>
</template>

<script setup>
import HotelForm from "@/components/hotel/HotelForm.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["hotels.update"],
  layout: "app",
});

const route = useRoute();
const hotelSlug = computed(() => route.params.hotelSlug);

const client = useSanctumClient();
const saving = ref(false);
const errors = ref({});

const { data, pending } = await useLazySanctumFetch(
  () => `/api/hotels/${hotelSlug.value}`,
  { key: () => `hotel-master-edit-${hotelSlug.value}` }
);

const hotel = computed(() => data.value?.data);

usePageMeta(null, {
  title: computed(() => `Edit · ${hotel.value?.name ?? "Hotel"} · Master`),
});

const handleSubmit = async (payload) => {
  saving.value = true;
  errors.value = {};
  try {
    const response = await client(`/api/hotels/${hotelSlug.value}`, {
      method: "PUT",
      body: payload,
    });
    toast.success("Hotel updated");
    await navigateTo(`/hotels-master/${response.data.slug}`);
  } catch (err) {
    if (err?.response?.status === 422 && err?.data?.errors) {
      errors.value = err.data.errors;
    }
    toast.error("Failed to update hotel", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
};
</script>
