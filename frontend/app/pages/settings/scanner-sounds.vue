<template>
  <div class="mx-auto space-y-6 pt-4 pb-16 lg:max-w-4xl">
    <div class="flex items-center gap-x-2.5">
      <Icon name="hugeicons:volume-high" class="size-5 sm:size-6" />
      <h1 class="page-title">Scanner Sounds</h1>
    </div>

    <p class="text-muted-foreground text-sm sm:text-base tracking-tight max-w-2xl">
      Global notification sounds played on the check-in scanner when a ticket is scanned. Applies to every event; staff devices play these on each result.
    </p>

    <div v-if="loading" class="flex justify-center py-10">
      <Spinner class="size-6" />
    </div>

    <ScannerSoundsForm
      v-else
      :model-value="sounds"
      :saving="saving"
      submit-label="Save Sounds"
      @submit="handleSubmit"
    />
  </div>
</template>

<script setup>
import ScannerSoundsForm from "@/components/scanner-sounds/ScannerSoundsForm.vue";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["sanctum:auth", "permission"],
  permissions: ["app_settings.update"],
  layout: "app",
});

usePageMeta(null, { title: "Scanner Sounds · Settings" });

const client = useSanctumClient();
const sounds = ref({});
const loading = ref(true);
const saving = ref(false);

onMounted(async () => {
  try {
    const res = await client("/api/app-settings/scan_sounds");
    sounds.value = res?.value ?? {};
  } catch (err) {
    toast.error("Failed to load scanner sounds", { description: err?.data?.message || err?.message });
  } finally {
    loading.value = false;
  }
});

const handleSubmit = async (payload) => {
  saving.value = true;
  try {
    const { tmp_success, tmp_failed, delete_success, delete_failed, ...value } = payload;
    const res = await client("/api/app-settings/scan_sounds", {
      method: "PUT",
      body: { value, tmp_success, tmp_failed, delete_success, delete_failed },
    });
    sounds.value = res?.value ?? value;
    toast.success("Scanner sounds saved");
  } catch (err) {
    toast.error("Failed to save scanner sounds", { description: err?.data?.message || err?.message });
  } finally {
    saving.value = false;
  }
};
</script>
