<template>
  <div class="flex gap-1 sm:gap-2">
    <!-- Export Dialog -->
    <DialogResponsive v-model:open="exportOpen" dialog-max-width="32rem">
      <template #trigger="{ open }">
        <button
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
          @click="handleExportOpen(open)"
        >
          <Icon name="hugeicons:file-export" class="size-4 shrink-0" />
          <span>Export</span>
        </button>
      </template>

      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">
            Export Roles & Permissions
          </div>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Copy this JSON and import it in another environment.
          </p>

          <div v-if="exportPending" class="mt-4">
            <Skeleton class="h-64 w-full" />
          </div>
          <div v-else-if="exportError" class="mt-4">
            <p class="text-destructive text-sm tracking-tight">
              Failed to load data. Please try again.
            </p>
          </div>
          <div v-else class="relative mt-4">
            <Textarea
              :model-value="exportJson"
              readonly
              class="bg-muted/50 h-64 font-mono text-xs"
            />
            <ButtonCopy v-if="exportJson" :text="exportJson" class="absolute top-2 right-2" />
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Import Dialog -->
    <DialogResponsive v-model:open="importOpen" dialog-max-width="32rem">
      <template #trigger="{ open }">
        <button
          class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98"
          @click="open()"
        >
          <Icon name="hugeicons:file-import" class="size-4 shrink-0" />
          <span>Import</span>
        </button>
      </template>

      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-foreground text-lg font-semibold tracking-tight">
            Import Roles & Permissions
          </div>

          <!-- Step 1: Paste JSON -->
          <template v-if="importStep === 'paste'">
            <p class="text-muted-foreground mt-1 text-sm tracking-tight">
              Paste the exported JSON to sync roles and permissions.
            </p>
            <Textarea
              v-model="importJson"
              placeholder="Paste exported JSON here"
              class="mt-4 h-48 font-mono text-xs"
            />
            <p v-if="parseError" class="text-destructive mt-2 text-sm tracking-tight">
              {{ parseError }}
            </p>
            <div class="mt-3 flex justify-end">
              <Button size="sm" :disabled="!importJson.trim()" @click="handlePreview">
                <Spinner v-if="previewPending" class="size-4" />
                <span v-else>Preview Changes</span>
              </Button>
            </div>
          </template>

          <!-- Step 2: Preview -->
          <template v-else-if="importStep === 'preview'">
            <div class="mt-4 space-y-3">
              <template v-if="hasChanges">
                <div
                  v-if="previewData.permissions_to_create.length > 0"
                  class="border-border rounded-md border p-3 space-y-2"
                >
                  <div class="text-sm font-medium tracking-tight">
                    {{ previewData.permissions_to_create.length }} new permission{{
                      previewData.permissions_to_create.length > 1 ? "s" : ""
                    }}
                    will be created
                  </div>
                  <div class="flex flex-col gap-1">
                    <span
                      v-for="perm in previewData.permissions_to_create"
                      :key="perm"
                      class="text-sm tracking-tight"
                    >
                      {{ perm }}
                    </span>
                  </div>
                </div>

                <div
                  v-if="previewData.roles_to_create.length > 0"
                  class="border-border rounded-md border p-3 space-y-2"
                >
                  <div class="text-sm font-medium tracking-tight">
                    {{ previewData.roles_to_create.length }} new role{{
                      previewData.roles_to_create.length > 1 ? "s" : ""
                    }}
                    will be created
                  </div>
                  <div class="flex flex-col gap-1">
                    <span
                      v-for="role in previewData.roles_to_create"
                      :key="role"
                      class="text-sm tracking-tight"
                    >
                      {{ role }}
                    </span>
                  </div>
                </div>

                <div
                  v-for="(sync, roleName) in previewData.roles_to_sync"
                  :key="roleName"
                  class="border-border rounded-md border p-3 space-y-2"
                >
                  <div class="text-sm font-medium tracking-tight">{{ roleName }}</div>
                  <div class="flex flex-col gap-1">
                    <span
                      v-for="perm in sync.added"
                      :key="'add-' + perm"
                      class="text-success-foreground text-sm tracking-tight"
                    >
                      + {{ perm }}
                    </span>
                    <span
                      v-for="perm in sync.removed"
                      :key="'rm-' + perm"
                      class="text-destructive text-sm tracking-tight"
                    >
                      - {{ perm }}
                    </span>
                  </div>
                </div>
              </template>

              <div v-else class="py-4 text-center text-sm tracking-tight">
                Everything is already in sync.
              </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
              <Button variant="outline" size="sm" @click="importStep = 'paste'"> Back </Button>
              <Button v-if="hasChanges" size="sm" :disabled="applyPending" @click="handleApply">
                <Spinner v-if="applyPending" class="size-4" />
                <span v-else>Confirm Import</span>
              </Button>
            </div>
          </template>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

const emit = defineEmits(["success"]);

// Export state
const exportOpen = ref(false);
const exportJson = ref("");
const exportPending = ref(false);
const exportError = ref(false);

const handleExportOpen = async (openFn) => {
  openFn();
  exportPending.value = true;
  exportError.value = false;
  exportJson.value = "";

  try {
    const client = useSanctumClient();
    const response = await client("/api/roles-permissions/export");
    exportJson.value = JSON.stringify(response.data, null, 2);
  } catch (e) {
    exportError.value = true;
  } finally {
    exportPending.value = false;
  }
};

// Import state
const importOpen = ref(false);
const importJson = ref("");
const importStep = ref("paste");
const parseError = ref("");
const previewPending = ref(false);
const applyPending = ref(false);
const previewData = ref({
  permissions_to_create: [],
  roles_to_create: [],
  roles_to_sync: {},
});

const hasChanges = computed(() => {
  return (
    previewData.value.permissions_to_create.length > 0 ||
    previewData.value.roles_to_create.length > 0 ||
    Object.keys(previewData.value.roles_to_sync).length > 0
  );
});

// Reset import state when dialog closes
watch(importOpen, (val) => {
  if (!val) {
    importJson.value = "";
    importStep.value = "paste";
    parseError.value = "";
    previewData.value = { permissions_to_create: [], roles_to_create: [], roles_to_sync: {} };
  }
});

const handlePreview = async () => {
  parseError.value = "";

  let parsed;
  try {
    parsed = JSON.parse(importJson.value);
  } catch {
    parseError.value = "Invalid JSON format.";
    return;
  }

  if (!parsed.permissions || !parsed.roles) {
    parseError.value = 'JSON must contain "permissions" and "roles" keys.';
    return;
  }

  previewPending.value = true;
  try {
    const client = useSanctumClient();
    const response = await client("/api/roles-permissions/import", {
      method: "POST",
      body: { ...parsed, preview: true },
    });
    previewData.value = response.data;
    importStep.value = "preview";
  } catch (e) {
    parseError.value = e?.data?.message || "Failed to preview changes.";
  } finally {
    previewPending.value = false;
  }
};

const handleApply = async () => {
  let parsed;
  try {
    parsed = JSON.parse(importJson.value);
  } catch {
    return;
  }

  applyPending.value = true;
  try {
    const client = useSanctumClient();
    const response = await client("/api/roles-permissions/import", {
      method: "POST",
      body: { ...parsed, preview: false },
    });

    const data = response.data;
    const parts = [];
    if (data.permissions_created > 0)
      parts.push(`${data.permissions_created} permission(s) created`);
    if (data.roles_created > 0) parts.push(`${data.roles_created} role(s) created`);
    if (data.roles_synced > 0) parts.push(`${data.roles_synced} role(s) synced`);

    toast.success("Import completed", {
      description: parts.length > 0 ? parts.join(", ") : "No changes needed",
    });

    importOpen.value = false;
    emit("success");
  } catch (e) {
    toast.error("Import failed", {
      description: e?.data?.message || e?.message || "An error occurred",
    });
  } finally {
    applyPending.value = false;
  }
};
</script>
