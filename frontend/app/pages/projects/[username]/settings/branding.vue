<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Branding</h2>
      <p class="page-description">
        Logo, company info, and footer used on every Invoice & Receipt PDF for this project - Hotel
        Reservations, Tickets, and other documents. Leave the override off to fall back to the
        global branding.
      </p>
    </div>

    <div v-if="canEditBranding" class="frame">
      <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
        <Icon name="hugeicons:paint-board" class="mt-0.5 size-5 shrink-0" />
        <div class="min-w-0 flex-1 space-y-1">
          <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <h3 class="text-base font-semibold tracking-tight">PDF Branding</h3>
            <Badge v-if="brandingEnabled" variant="info" icon="hugeicons:paint-board">
              Override active
            </Badge>
          </div>
          <p class="text-muted-foreground text-sm tracking-tight">
            {{
              brandingEnabled
                ? "This project uses its own logo, company info, and footer on Invoice and Receipt PDFs."
                : "Invoice and Receipt PDFs fall back to global branding. Enable to override for white-label projects."
            }}
          </p>
        </div>
        <Switch v-model="brandingEnabled" />
      </div>

      <div class="frame-panel">
        <BrandingForm
          v-if="brandingEnabled && !brandingLoading"
          :model-value="brandingDraft"
          :saving="brandingSaving"
          submit-label="Save Branding"
          @submit="handleBrandingSubmit"
        />

        <div v-else-if="brandingEnabled && brandingLoading" class="flex justify-center py-4">
          <Spinner class="size-5" />
        </div>

        <div v-else-if="!brandingEnabled && brandingDraft?.company_name" class="flex justify-end">
          <Button variant="outline" :disabled="brandingSaving" @click="clearBranding">
            <Icon v-if="brandingSaving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
            Clear Custom Branding
          </Button>
        </div>

        <p v-else class="text-muted-foreground text-sm tracking-tight">
          Global branding is used on Invoice and Receipt PDFs.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import BrandingForm from "@/components/branding/BrandingForm.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

definePageMeta({
  middleware: ["permission"],
  permissions: ["events.update_branding"],
});

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Branding · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const { hasPermission } = usePermission();
const canEditBranding = computed(() => hasPermission("events.update_branding"));

const brandingEnabled = ref(false);
const brandingDraft = ref({});
const brandingLoading = ref(false);
const brandingSaving = ref(false);

async function loadBranding() {
  if (!canEditBranding.value) return;
  brandingLoading.value = true;
  try {
    const res = await client(`/api/projects/${route.params.username}/branding`);
    const value = res?.branding;
    brandingEnabled.value = value !== null && value !== undefined;
    brandingDraft.value = value ?? {};
  } catch (err) {
    // silent - allow user to enable later
  } finally {
    brandingLoading.value = false;
  }
}

const handleBrandingSubmit = async (payload) => {
  brandingSaving.value = true;
  try {
    const { tmp_logo, delete_logo, ...branding } = payload;
    const res = await client(`/api/projects/${route.params.username}/branding`, {
      method: "PUT",
      body: { branding, tmp_logo, delete_logo },
    });
    brandingDraft.value = res?.branding ?? branding;
    brandingEnabled.value = true;
    toast.success("Project branding saved");
  } catch (err) {
    toast.error("Failed to save branding", { description: err?.data?.message || err?.message });
  } finally {
    brandingSaving.value = false;
  }
};

const clearBranding = async () => {
  brandingSaving.value = true;
  try {
    await client(`/api/projects/${route.params.username}/branding`, {
      method: "PUT",
      body: { branding: null },
    });
    brandingDraft.value = {};
    toast.success("Custom branding cleared. Using global branding.");
  } catch (err) {
    toast.error("Failed to clear branding", { description: err?.data?.message || err?.message });
  } finally {
    brandingSaving.value = false;
  }
};

onMounted(() => {
  loadBranding();
});
</script>
