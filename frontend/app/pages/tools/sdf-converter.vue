<script setup>
import { computed, onMounted, ref } from "vue";
import { toast } from "vue-sonner";
import SdfThumbnail from "@/components/shaders/SdfThumbnail.vue";
import ButtonCopy from "@/components/ui/button-copy/ButtonCopy.vue";
import { Checkbox } from "@/components/ui/checkbox";
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "@/components/ui/empty";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

definePageMeta({
  layout: "app",
  middleware: [
    "sanctum:auth",
    () => {
      const { hasAnyRole } = usePermission();
      if (!hasAnyRole(["master", "admin"])) {
        return createError({ statusCode: 403, statusMessage: "Forbidden", fatal: true });
      }
    },
  ],
});

usePageMeta(null, { title: "SDF Converter" });

const client = useSanctumClient();

const fileInput = ref(null);
const converting = ref(false);
const lastResult = ref(null);
const sourcePreview = ref("");
const sourceName = ref("");

const files = ref([]);
const loadingList = ref(false);
const selected = ref([]);
const deleteTarget = ref([]);
const deleteOpen = ref(false);
const deleting = ref(false);

const allSelected = computed(
  () => files.value.length > 0 && selected.value.length === files.value.length
);

function isSelected(name) {
  return selected.value.includes(name);
}

function toggleRow(name, value) {
  selected.value = value
    ? [...selected.value, name]
    : selected.value.filter((n) => n !== name);
}

function toggleAll(value) {
  selected.value = value ? files.value.map((f) => f.filename) : [];
}

function formatDate(ts) {
  return new Date(ts * 1000).toLocaleDateString(undefined, {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
}

async function fetchFiles() {
  loadingList.value = true;
  try {
    const res = await client("/api/shaders/sdf");
    files.value = res.data ?? [];
    selected.value = selected.value.filter((n) => files.value.some((f) => f.filename === n));
  } catch (e) {
    toast.error(e?.data?.message || "Failed to load saved files.");
  } finally {
    loadingList.value = false;
  }
}

function pickFile() {
  fileInput.value?.click();
}

async function onFile(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file) {
    return;
  }

  if (sourcePreview.value) {
    URL.revokeObjectURL(sourcePreview.value);
  }
  sourcePreview.value = URL.createObjectURL(file);
  sourceName.value = file.name;
  lastResult.value = null;
  converting.value = true;

  try {
    const formData = new FormData();
    formData.append("file", file);
    lastResult.value = await client("/api/shaders/sdf", { method: "POST", body: formData });
    toast.success("Logo converted to SDF.");
    await fetchFiles();
  } catch (e) {
    toast.error(e?.data?.message || e?.response?._data?.message || "Failed to convert the file.");
  } finally {
    converting.value = false;
  }
}

async function downloadFile(url, filename) {
  try {
    const response = await fetch(url);
    const blob = await response.blob();
    const objectUrl = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = objectUrl;
    a.download = filename || "shape_sdf.bin";
    a.click();
    setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
  } catch {
    toast.error("Could not download the file (check storage CORS).");
  }
}

function askDelete(filenames) {
  deleteTarget.value = filenames;
  deleteOpen.value = true;
}

async function confirmDelete() {
  const names = deleteTarget.value;
  deleting.value = true;
  try {
    if (names.length === 1) {
      await client(`/api/shaders/sdf/${names[0]}`, { method: "DELETE" });
    } else {
      await client("/api/shaders/sdf/bulk", { method: "DELETE", body: { filenames: names } });
    }
    toast.success(`Deleted ${names.length} file${names.length === 1 ? "" : "s"}.`);
    selected.value = [];
    deleteOpen.value = false;
    if (lastResult.value && names.includes(lastResult.value.filename)) {
      lastResult.value = null;
    }
    await fetchFiles();
  } catch (e) {
    toast.error(e?.data?.message || "Failed to delete.");
  } finally {
    deleting.value = false;
  }
}

onMounted(fetchFiles);
</script>

<template>
  <div class="mx-auto max-w-2xl space-y-6 pt-4 pb-16">
    <div class="space-y-1">
      <div class="flex items-center gap-x-2.5">
        <Icon name="hugeicons:exchange-01" class="size-5 sm:size-6" />
        <h1 class="page-title">SDF Converter</h1>
      </div>
      <p class="page-description">
        Convert an SVG/PNG logo into a Signed Distance Field for shader shape effects (Glass, Neon,
        Emboss, Crystal). Copy the resulting URL into the Custom Shape field in the shader editor.
      </p>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Convert a logo</div>
        <div class="frame-description">SVG (recommended) or PNG with transparency, up to 512 KB.</div>
      </div>
      <div class="frame-panel space-y-4">
        <div class="flex items-center gap-x-2">
          <Button :disabled="converting" @click="pickFile">
            <Icon
              :name="converting ? 'hugeicons:loading-03' : 'hugeicons:upload-01'"
              :class="converting && 'animate-spin'"
            />
            {{ converting ? "Converting…" : "Choose file" }}
          </Button>
          <span v-if="sourceName" class="text-muted-foreground truncate text-sm tracking-tight">
            {{ sourceName }}
          </span>
        </div>
        <input
          ref="fileInput"
          type="file"
          accept=".svg,.png,image/svg+xml,image/png"
          class="hidden"
          @change="onFile"
        />
        <div
          v-if="sourcePreview"
          class="bg-muted/30 ring-border flex items-center justify-center rounded-lg p-6 ring-1"
        >
          <img :src="sourcePreview" :alt="sourceName" class="max-h-40 w-auto" />
        </div>
        <div v-if="lastResult" class="space-y-2">
          <Label>SDF URL</Label>
          <div class="flex items-center gap-x-2">
            <Input :model-value="lastResult.url" readonly class="font-mono text-xs sm:text-sm" />
            <ButtonCopy :text="lastResult.url" />
          </div>
          <p class="text-muted-foreground text-xs">
            Paste this into the Custom Shape field of a Glass/Neon/Emboss/Crystal layer.
          </p>
        </div>
      </div>
    </div>

    <div class="frame">
      <div class="frame-header">
        <div class="flex items-center justify-between gap-x-2">
          <div class="frame-title">Saved SDF files</div>
          <Button
            v-if="selected.length"
            variant="destructive"
            size="sm"
            :disabled="deleting"
            @click="askDelete([...selected])"
          >
            <Icon name="hugeicons:delete-02" />
            Delete ({{ selected.length }})
          </Button>
        </div>
        <div class="frame-description">Stored conversions you can reuse, download, or delete.</div>
      </div>
      <div class="frame-panel">
        <div v-if="loadingList" class="space-y-2">
          <Skeleton v-for="i in 4" :key="i" class="h-11 w-full" />
        </div>

        <Empty v-else-if="!files.length">
          <EmptyHeader>
            <EmptyMedia variant="icon">
              <Icon name="hugeicons:folder-01" />
            </EmptyMedia>
            <EmptyTitle>No saved files yet</EmptyTitle>
            <EmptyDescription>Convert a logo above to create your first SDF.</EmptyDescription>
          </EmptyHeader>
        </Empty>

        <Table v-else>
          <TableHeader>
            <TableRow>
              <TableHead class="w-10">
                <Checkbox :model-value="allSelected" aria-label="Select all" @update:model-value="toggleAll" />
              </TableHead>
              <TableHead>File</TableHead>
              <TableHead class="w-20">Size</TableHead>
              <TableHead class="w-28">Modified</TableHead>
              <TableHead class="w-px text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="f in files" :key="f.filename">
              <TableCell>
                <Checkbox
                  :model-value="isSelected(f.filename)"
                  :aria-label="`Select ${f.filename}`"
                  @update:model-value="(v) => toggleRow(f.filename, v)"
                />
              </TableCell>
              <TableCell>
                <div class="flex min-w-0 items-center gap-x-2.5">
                  <SdfThumbnail :url="f.url" />
                  <span class="truncate font-mono text-xs sm:text-sm">{{ f.filename }}</span>
                </div>
              </TableCell>
              <TableCell class="text-muted-foreground text-sm tracking-tight">
                {{ Math.round(f.bytes / 1024) }} KB
              </TableCell>
              <TableCell class="text-muted-foreground text-sm tracking-tight">
                {{ formatDate(f.modified_at) }}
              </TableCell>
              <TableCell>
                <div class="flex items-center justify-end gap-x-1">
                  <ButtonCopy :text="f.url" />
                  <Button
                    v-tippy="'Download .bin'"
                    variant="ghost"
                    size="iconSm"
                    @click="downloadFile(f.url, f.filename)"
                  >
                    <Icon name="hugeicons:download-01" />
                  </Button>
                  <Button
                    v-tippy="'Delete'"
                    variant="ghost"
                    size="iconSm"
                    class="hover:text-destructive"
                    @click="askDelete([f.filename])"
                  >
                    <Icon name="hugeicons:delete-02" />
                  </Button>
                </div>
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </div>

    <DialogResponsive v-model:open="deleteOpen">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-lg font-semibold tracking-tighter">
            Delete {{ deleteTarget.length }} file{{ deleteTarget.length === 1 ? "" : "s" }}?
          </div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            This permanently removes the SDF from storage. Any shader still pointing at its URL will
            stop rendering the shape.
          </p>
          <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" :disabled="deleting" @click="deleteOpen = false">Cancel</Button>
            <Button variant="destructive" :disabled="deleting" @click="confirmDelete">
              <Icon v-if="deleting" name="hugeicons:loading-03" class="animate-spin" />
              Delete
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>
