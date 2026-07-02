<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="520px" :overflow-content="true">
    <template #trigger="{ open }">
      <slot name="trigger" :open="open" />
    </template>

    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-6">
          <div>
            <h2 class="text-foreground text-lg font-semibold tracking-tight">Import Rundown</h2>
            <p class="text-muted-foreground mt-1 text-sm tracking-tight">
              Upload an XLSX or JSON file to add rundown items to this event.
            </p>
          </div>

          <template v-if="importing">
            <div class="space-y-4">
              <div class="space-y-2">
                <div class="flex items-center justify-between text-sm tracking-tight">
                  <span class="text-muted-foreground">Importing...</span>
                  <span class="font-medium tabular-nums">{{ progress?.percentage ?? 0 }}%</span>
                </div>
                <Progress :model-value="progress?.percentage ?? 0" indicator-class="bg-success" />
                <p
                  v-if="progress && progress.total_rows > 0"
                  class="text-muted-foreground text-xs sm:text-sm tracking-tight tabular-nums"
                >
                  {{ progress.processed_rows }} / {{ progress.total_rows }} rows
                </p>
              </div>
            </div>
          </template>

          <template v-else>
            <div class="bg-muted/50 rounded-lg border p-4">
              <div class="flex items-start gap-3">
                <Icon name="lucide:info" class="text-foreground mt-0.5 size-5 shrink-0" />
                <div class="space-y-2 text-sm tracking-tight">
                  <p class="font-medium">How it works</p>
                  <ul class="text-muted-foreground list-inside list-disc space-y-1">
                    <li>
                      Items are <strong>appended</strong> to the existing rundown. Existing items stay
                      untouched.
                    </li>
                    <li>
                      Supported formats: <strong>.xlsx</strong> and <strong>.json</strong>.
                    </li>
                    <li>Translatable fields use <code>en</code> and <code>id</code> locales.</li>
                    <li>Posters and media are not imported.</li>
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
                  <span>Download XLSX template</span>
                </button>
              </div>

              <div class="space-y-1.5">
                <label class="text-sm font-medium tracking-tight">Upload file</label>
                <InputFile
                  v-model="uploadedFiles"
                  :accepted-file-types="[
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/json',
                  ]"
                  max-file-size="10MB"
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
                class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <span>Import</span>
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

const emit = defineEmits(["imported"]);

const isOpen = ref(false);
const uploadedFiles = ref([]);
const downloadPending = ref(false);

const sanctumFetch = useSanctumClient();
const { progress, importing, startImport, reset } = useImportProgress();

const baseUrl = computed(
  () => `/api/projects/${props.username}/events/${props.eventSlug}/rundown-items`,
);

watch(isOpen, (open) => {
  if (!open && importing.value) {
    isOpen.value = true;
  }
});

watch(
  () => progress.value?.status,
  (status) => {
    if (status === "completed") {
      const p = progress.value;
      const errorCount = p?.errors?.length || 0;

      if (errorCount > 0) {
        toast.success("Imported with errors", {
          description: `${p.imported_count} item(s) imported, ${errorCount} row(s) failed`,
        });
      } else {
        toast.success(
          p?.imported_count
            ? `Imported ${p.imported_count} rundown item(s)`
            : "Import complete",
        );
      }

      isOpen.value = false;
      uploadedFiles.value = [];
      reset();
      emit("imported");
    }

    if (status === "failed") {
      toast.error("Failed to import", {
        description: progress.value?.error_message || "An error occurred",
      });
      reset();
    }
  },
);

const handleDownloadTemplate = async () => {
  try {
    downloadPending.value = true;

    const response = await sanctumFetch(`${baseUrl.value}/import/template`, {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "rundown_import_template.xlsx";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Template downloaded");
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
    await startImport(`${baseUrl.value}/import`, {
      file: uploadedFiles.value[0],
    });
  } catch (error) {
    console.error("Failed to start import:", error);
    toast.error("Failed to import", {
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
