<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:image-02" class="size-5 sm:size-6" />
      <h1 class="page-title">Global Branding</h1>
    </div>

    <p class="text-muted-foreground text-sm sm:text-base tracking-tight max-w-2xl">
      Brand identity used on Hotel Reservation Invoice & Receipt PDF. Per-event override is available in Event details.
    </p>

    <div v-if="loading" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <BrandingForm
      v-else
      :model-value="branding"
      :saving="saving"
      submit-label="Save Branding"
      @submit="handleSubmit"
    />
  </div>
</template>

<script setup>
import BrandingForm from "@/components/branding/BrandingForm.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["app_settings.update"],
  layout: "app",
});

usePageMeta(null, { title: "Branding · Settings" });

const client = useSanctumClient();
const branding = ref({});
const loading = ref(true);
const saving = ref(false);

onMounted(async () => {
  try {
    const res = await client("/api/app-settings/branding");
    branding.value = res?.value ?? {};
  } catch (err) {
    toast.error("Failed to load branding", { description: err?.data?.message || err?.message });
  } finally {
    loading.value = false;
  }
});

const handleSubmit = async (payload) => {
  saving.value = true;
  try {
    const { tmp_logo, delete_logo, ...value } = payload;
    const res = await client("/api/app-settings/branding", {
      method: "PUT",
      body: { value, tmp_logo, delete_logo },
    });
    branding.value = res?.value ?? value;
    toast.success("Branding saved");
  } catch (err) {
    toast.error("Failed to save branding", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};
</script>
