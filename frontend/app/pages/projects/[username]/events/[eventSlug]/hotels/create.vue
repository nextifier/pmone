<template>
  <div class="mx-auto space-y-6 pb-16 lg:max-w-4xl">
    <div class="flex items-center gap-x-2.5">
      <NuxtLink :to="`${eventBase}/hotels`" class="hover:bg-muted text-muted-foreground inline-flex size-8 items-center justify-center rounded-md">
        <Icon name="lucide:arrow-left" class="size-4" />
      </NuxtLink>
      <h1 class="page-title">Add Hotel</h1>
    </div>

    <HotelForm
      :saving="saving"
      :errors="errors"
      submit-label="Create Hotel"
      @submit="handleSubmit"
      @cancel="navigateTo(`${eventBase}/hotels`)"
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

const props = defineProps({
  event: Object,
  project: Object,
});

const route = useRoute();

const eventBase = computed(
  () => `/projects/${route.params.username}/events/${route.params.eventSlug}`
);

usePageMeta(null, {
  title: computed(() => `Add Hotel · ${props.event?.title || "Event"}`),
});

const client = useSanctumClient();
const saving = ref(false);
const errors = ref({});

const handleSubmit = async (payload) => {
  saving.value = true;
  errors.value = {};
  try {
    const response = await client(`/api/events/${props.event.id}/hotels`, {
      method: "POST",
      body: payload,
    });
    toast.success("Hotel created");
    await navigateTo(`${eventBase.value}/hotels/${response.data.slug}`);
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
