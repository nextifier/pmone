<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="500px" :overflow-content="true">
    <template #trigger="{ open }">
      <slot name="trigger" :open="open" />
    </template>

    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-6">
          <div>
            <h2 class="text-foreground text-lg font-semibold tracking-tight">Import Brands</h2>
            <p class="text-muted-foreground mt-1 text-sm tracking-tight">
              Upload an Excel file to import multiple brands into this event
            </p>
          </div>

          <!-- Progress section -->
          <template v-if="importing">
            <div class="space-y-4">
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm tracking-tight">
                  <span class="text-muted-foreground">Importing brands...</span>
                  <span class="font-medium tabular-nums">{{ progress?.percentage ?? 0 }}%</span>
                </div>
                <Progress :model-value="progress?.percentage ?? 0" indicator-class="bg-success" />
                <p v-if="progress && progress.total_rows > 0" class="text-muted-foreground text-xs sm:text-sm tracking-tight tabular-nums">
                  {{ progress.processed_rows }} / {{ progress.total_rows }} rows
                </p>
              </div>
            </div>
          </template>

          <!-- Upload section -->
          <template v-else>
            <div class="bg-muted/50 rounded-lg border p-4">
              <div class="flex items-start gap-3">
                <Icon name="lucide:info" class="text-foreground mt-0.5 size-5 shrink-0" />
                <div class="space-y-2 text-sm tracking-tight">
                  <p class="font-medium">Import Instructions:</p>
                  <ul class="text-muted-foreground list-inside list-disc space-y-1">
                    <li>Download the template file with sample data</li>
                    <li>Only <strong>Brand Name</strong> is required</li>
                    <li>Supported formats: CSV, XLS, XLSX (max 5MB)</li>
                    <li>
                      Status can be "Active", "Draft", or "Cancelled" (defaults to "Active")
                    </li>
                    <li>
                      Booth Type accepts any booth type label, such as "Raw Space", "Standard Shell
                      Scheme", or "Artist Alley"
                    </li>
                    <li>If a brand name already exists, it will be linked to the existing brand</li>
                    <li>Brands already in this event will be skipped</li>
                    <li>PIC Email will be created as exhibitor user if not found</li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="space-y-3">
              <div>
                <button
                  @click="handleDownloadTemplate"
                  :disabled="downloadPending"
                  class="border-border hover:bg-muted flex w-full items-center justify-center gap-x-2 rounded-lg border px-4 py-2.5 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
                >
                  <Spinner v-if="downloadPending" class="size-4 shrink-0" />
                  <Icon v-else name="lucide:download" class="size-4 shrink-0" />
                  <span>Download Template</span>
                </button>
              </div>

              <div class="space-y-1.5">
                <label class="text-sm font-medium tracking-tight">Upload File</label>
                <InputFile
                  v-model="uploadedFiles"
                  :accepted-file-types="[
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                  ]"
                  max-file-size="5MB"
                />
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <button
                class="border-border hover:bg-muted rounded-lg border px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
                @click="handleCancel"
              >
                Cancel
              </button>
              <button
                @click="handleImport"
                :disabled="!uploadedFiles.length"
                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Import Brands
              </button>
            </div>
          </template>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import InputFile from "@/components/InputFile.vue";
import { Progress } from "@/components/ui/progress";
import { toast } from "vue-sonner";

const props = defineProps({
  username: { type: String, required: true },
  eventSlug: { type: String, required: true },
});

const emit = defineEmits(["imported", "importErrors"]);

const isOpen = ref(false);
const uploadedFiles = ref([]);
const downloadPending = ref(false);

const sanctumFetch = useSanctumClient();
const { progress, importing, startImport, reset } = useImportProgress();

const apiBase = computed(
  () => `/api/projects/${props.username}/events/${props.eventSlug}/brands`
);

// Prevent closing dialog while importing
watch(isOpen, (open) => {
  if (!open && importing.value) {
    isOpen.value = true;
  }
});

// Watch for completion/failure
watch(
  () => progress.value?.status,
  (status) => {
    if (status === "completed") {
      const p = progress.value;
      const errorCount = p?.errors?.length || 0;

      if (errorCount > 0) {
        toast.success("Import completed with errors", {
          description: `${p.imported_count} brand(s) imported, ${errorCount} row(s) failed`,
        });
        emit("importErrors", { errors: p.errors, importedCount: p.imported_count || 0, skippedCount: p.skipped_count || 0 });
        isOpen.value = false;
        uploadedFiles.value = [];
        reset();
        emit("imported");
      } else {
        const parts = [`${p.imported_count} brand(s) imported`];
        if (p.skipped_count > 0) parts.push(`${p.skipped_count} skipped (already exist)`);
        toast.success("Brands imported successfully", {
          description: parts.join(', '),
        });
        isOpen.value = false;
        uploadedFiles.value = [];
        reset();
        emit("imported");
      }
    }

    if (status === "failed") {
      toast.error("Failed to import brands", {
        description: progress.value?.error_message || "An error occurred",
      });
      reset();
    }
  },
);

const handleDownloadTemplate = async () => {
  try {
    downloadPending.value = true;

    const response = await sanctumFetch(`${apiBase.value}/import/template`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "brand_events_import_template.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Template downloaded successfully");
  } catch (error) {
    console.error("Failed to download template:", error);
    toast.error("Failed to download template", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    downloadPending.value = false;
  }
};

const handleImport = async () => {
  if (!uploadedFiles.value.length) {
    toast.error("Please upload a file");
    return;
  }

  try {
    await startImport(`${apiBase.value}/import`, {
      file: uploadedFiles.value[0],
    });
  } catch (error) {
    console.error("Failed to start import:", error);
    toast.error("Failed to start import", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
    reset();
  }
};

const handleCancel = () => {
  isOpen.value = false;
  uploadedFiles.value = [];
};
</script>
