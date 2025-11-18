<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <button
        :disabled="isExporting || disabled"
        class="border-border hover:bg-muted flex h-8 items-center gap-x-1 rounded-md border px-2 py-1 text-sm tracking-tight active:scale-98 disabled:cursor-not-allowed disabled:opacity-50"
      >
        <Spinner v-if="isExporting" class="size-4 shrink-0" />
        <Icon v-else name="hugeicons:file-export" class="size-4 shrink-0" />
        <span>{{ isExporting ? "Exporting..." : "Export" }}</span>
        <Icon name="hugeicons:arrow-down-01" class="size-4 shrink-0" />
      </button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end">
      <DropdownMenuItem v-if="enableExcel" @click="handleExportToExcel">
        <Icon name="hugeicons:xls-01" class="size-4.5 shrink-0" />
        Export to Excel
      </DropdownMenuItem>
      <DropdownMenuItem @click="handleExportToPDF">
        <Icon name="hugeicons:pdf-01" class="size-4.5 shrink-0" />
        Export to PDF
      </DropdownMenuItem>
      <DropdownMenuItem @click="handleExportToJPG">
        <Icon name="hugeicons:jpg-01" class="size-4.5 shrink-0" />
        Export to JPG
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>

<script setup>
const props = defineProps({
  /**
   * Start date for the analytics data
   */
  startDate: {
    type: Object, // dayjs object
    required: true,
  },

  /**
   * End date for the analytics data
   */
  endDate: {
    type: Object, // dayjs object
    required: true,
  },

  /**
   * CSS selector for the element to export (for PDF and JPG)
   */
  exportSelector: {
    type: String,
    default: ".min-h-screen-offset",
  },

  /**
   * Base filename prefix for exports
   */
  filenamePrefix: {
    type: String,
    default: "analytics",
  },

  /**
   * Enable Excel export
   */
  enableExcel: {
    type: Boolean,
    default: true,
  },

  /**
   * Excel export handler function
   */
  onExcelExport: {
    type: Function,
    default: null,
  },

  /**
   * Disabled state
   */
  disabled: {
    type: Boolean,
    default: false,
  },
});

// Use composable for PDF and JPG exports
const { isExporting, exportToPDF, exportToJPG } = usePageExport();

const handleExportToPDF = () => {
  const startDateStr = props.startDate.format("YYYY-MM-DD");
  const endDateStr = props.endDate.format("YYYY-MM-DD");
  exportToPDF(props.exportSelector, {
    filename: `${props.filenamePrefix}_${startDateStr}_to_${endDateStr}.pdf`,
  });
};

const handleExportToJPG = () => {
  const startDateStr = props.startDate.format("YYYY-MM-DD");
  const endDateStr = props.endDate.format("YYYY-MM-DD");
  exportToJPG(props.exportSelector, {
    filename: `${props.filenamePrefix}_${startDateStr}_to_${endDateStr}.jpg`,
  });
};

const handleExportToExcel = () => {
  if (props.onExcelExport) {
    props.onExcelExport();
  }
};
</script>
