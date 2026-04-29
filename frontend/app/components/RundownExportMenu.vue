<template>
  <DropdownMenu>
    <DropdownMenuTrigger asChild>
      <button
        type="button"
        class="border-border hover:bg-muted flex items-center gap-x-1 rounded-md border px-2.5 py-1.5 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
        :disabled="exporting"
      >
        <Spinner v-if="exporting" class="size-4 shrink-0" />
        <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
        <span class="hidden sm:inline">Export</span>
        <Icon name="lucide:chevron-down" class="size-3 shrink-0 opacity-60" />
      </button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-44">
      <DropdownMenuItem :disabled="exporting" class="gap-x-2" @click="handleExport('xlsx')">
        <Icon name="hugeicons:file-02" class="size-4" />
        Export as XLSX
      </DropdownMenuItem>
      <DropdownMenuItem :disabled="exporting" class="gap-x-2" @click="handleExport('json')">
        <Icon name="hugeicons:source-code" class="size-4" />
        Export as JSON
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup>
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { toast } from "vue-sonner";

const props = defineProps({
  username: { type: String, required: true },
  eventSlug: { type: String, required: true },
});

const exporting = ref(false);

const sanctumFetch = useSanctumClient();

const formatMeta = {
  xlsx: {
    path: "/export",
    extension: "xlsx",
    mime: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    label: "XLSX",
  },
  json: {
    path: "/export/json",
    extension: "json",
    mime: "application/json",
    label: "JSON",
  },
};

const handleExport = async (format) => {
  const meta = formatMeta[format];
  if (!meta) return;

  try {
    exporting.value = true;

    const url = `/api/projects/${props.username}/events/${props.eventSlug}/rundown-items${meta.path}`;
    const response = await sanctumFetch(url, { responseType: "blob" });

    const blob = new Blob([response], { type: meta.mime });
    const downloadUrl = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    const timestamp = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
    link.href = downloadUrl;
    link.download = `rundown_${props.eventSlug}_${timestamp}.${meta.extension}`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(downloadUrl);

    toast.success(`Exported as ${meta.label}`);
  } catch (error) {
    console.error("Failed to export rundown:", error);
    toast.error("Failed to export", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    exporting.value = false;
  }
};
</script>
