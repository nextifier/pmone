<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex items-center gap-x-2.5">
      <NuxtLink :to="`/hotels/${slug}`" class="hover:bg-muted text-muted-foreground inline-flex size-8 items-center justify-center rounded-md">
        <Icon name="lucide:arrow-left" class="size-4" />
      </NuxtLink>
      <h1 class="page-title">Edit Hotel</h1>
    </div>

    <div v-if="pending" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <HotelForm
      v-else-if="hotel"
      :initial="hotel"
      :saving="saving"
      submit-label="Save Changes"
      @submit="handleSubmit"
      @cancel="navigateTo(`/hotels/${slug}`)"
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
const slug = computed(() => route.params.slug);

usePageMeta(null, {
  title: computed(() => `Edit · ${hotel.value?.name ?? "Hotel"}`),
});

const client = useSanctumClient();
const saving = ref(false);

const { data, pending } = await useLazySanctumFetch(() => `/api/hotels/${slug.value}`, {
  key: () => `hotel-${slug.value}`,
});

const hotel = computed(() => data.value?.data);

const handleSubmit = async (payload) => {
  saving.value = true;
  try {
    const response = await client(`/api/hotels/${slug.value}`, {
      method: "PUT",
      body: payload,
    });
    toast.success("Hotel updated");
    await navigateTo(`/hotels/${response.data.slug}`);
  } catch (err) {
    toast.error("Failed to update hotel", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
};
</script>
