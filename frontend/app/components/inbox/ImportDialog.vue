<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="500px" :overflow-content="true">
    <template #trigger="{ open }">
      <slot name="trigger" :open="open" />
    </template>

    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="space-y-6">
          <div>
            <h2 class="text-primary text-lg font-semibold tracking-tight">Import Submissions</h2>
            <p class="text-muted-foreground mt-1 text-sm tracking-tight">
              Upload an Excel file to import multiple contact form submissions at once
            </p>
          </div>

          <div class="bg-muted/50 rounded-lg border p-4">
            <div class="flex items-start gap-3">
              <Icon name="lucide:info" class="text-primary mt-0.5 size-5 shrink-0" />
              <div class="space-y-2 text-sm tracking-tight">
                <p class="font-medium">Import Instructions:</p>
                <ul class="text-muted-foreground list-inside list-disc space-y-1">
                  <li>Download the template file with sample data</li>
                  <li><strong>Name</strong>, <strong>Email</strong>, and <strong>Project</strong> are required</li>
                  <li>Project name must match an existing project in the system</li>
                  <li>Supported formats: CSV, XLS, XLSX (max 5MB)</li>
                  <li>Status can be: New, In Progress, Completed, Archived</li>
                  <li>Brand Name, Phone, Subject, and Created At are optional</li>
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
              :disabled="!uploadedFiles.length || importPending"
              class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Spinner v-if="importPending" class="mr-2 inline size-4" />
              <span>Import Submissions</span>
            </button>
          </div>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import InputFile from "@/components/InputFile.vue";
import { toast } from "vue-sonner";

const emit = defineEmits(["imported"]);

const isOpen = ref(false);
const uploadedFiles = ref([]);
const downloadPending = ref(false);
const importPending = ref(false);

const sanctumFetch = useSanctumClient();

const handleDownloadTemplate = async () => {
  try {
    downloadPending.value = true;

    const response = await sanctumFetch("/api/contact-form-submissions/import/template", {
      responseType: "blob",
    });

    const blob = new Blob([response], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "inbox_import_template.xlsx";
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
    importPending.value = true;

    const response = await sanctumFetch("/api/contact-form-submissions/import", {
      method: "POST",
      body: {
        file: uploadedFiles.value[0],
      },
    });

    toast.success(response.message || "Submissions imported successfully", {
      description: response.imported_count
        ? `${response.imported_count} submission(s) imported`
        : undefined,
    });

    isOpen.value = false;
    uploadedFiles.value = [];
    emit("imported");
  } catch (error) {
    console.error("Failed to import submissions:", error);

    if (error?.data?.errors && Array.isArray(error.data.errors)) {
      const errorCount = error.data.errors.length;
      toast.error("Import completed with errors", {
        description: `${errorCount} row(s) failed to import. Check console for details.`,
      });
      console.table(error.data.errors);
    } else {
      toast.error("Failed to import submissions", {
        description: error?.data?.message || error?.message || "An error occurred",
      });
    }
  } finally {
    importPending.value = false;
  }
};

const handleCancel = () => {
  isOpen.value = false;
  uploadedFiles.value = [];
};
</script>
