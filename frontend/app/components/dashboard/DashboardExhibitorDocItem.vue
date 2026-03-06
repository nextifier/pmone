<template>
  <div class="rounded-lg border border-border bg-muted/30 p-3 sm:p-4">
    <!-- Header: title + status -->
    <div class="flex items-start gap-3">
      <div
        :class="[
          'flex size-8 shrink-0 items-center justify-center rounded-lg',
          status === 'completed'
            ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400'
            : status === 'needs_reagreement'
              ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'
              : 'bg-muted text-muted-foreground',
        ]"
      >
        <Icon :name="typeIcon" class="size-4" />
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <h4 class="text-sm font-medium tracking-tight">{{ doc.title }}</h4>
          <Badge v-if="doc.is_required && status !== 'completed'" variant="outline" class="text-xs tracking-tight font-normal">
            Required
          </Badge>
          <Badge
            v-if="status === 'needs_reagreement'"
            variant="outline"
            class="border-amber-200 bg-amber-50 text-xs tracking-tight font-normal text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400"
          >
            Updated
          </Badge>
          <Icon
            v-if="status === 'completed'"
            name="hugeicons:checkmark-circle-02"
            class="size-4 shrink-0 text-green-500"
          />
        </div>

        <!-- Description -->
        <div
          v-if="doc.description"
          class="prose prose-sm mt-1.5 max-w-none text-muted-foreground [&_p]:text-xs [&_p]:leading-relaxed [&_p]:tracking-tight sm:[&_p]:text-sm [&_ul]:text-xs sm:[&_ul]:text-sm [&_ol]:text-xs sm:[&_ol]:text-sm"
          v-html="doc.description"
        />
      </div>
    </div>

    <!-- File actions -->
    <div
      v-if="hasFiles"
      class="mt-3 flex flex-wrap gap-2"
      :class="{ 'ml-11': true }"
    >
      <!-- Event Rules / read-only docs: open PDF in new tab -->
      <template v-if="mode === 'view'">
        <a
          v-if="doc.template_en"
          :href="getMediaUrl(doc.template_en)"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
        >
          <Icon name="hugeicons:pdf-02" class="size-3.5 text-red-500" />
          View Document (EN)
          <Icon name="hugeicons:arrow-up-right-01" class="size-3 text-muted-foreground" />
        </a>
        <a
          v-if="doc.template_id"
          :href="getMediaUrl(doc.template_id)"
          target="_blank"
          rel="noopener"
          class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
        >
          <Icon name="hugeicons:pdf-02" class="size-3.5 text-red-500" />
          View Document (ID)
          <Icon name="hugeicons:arrow-up-right-01" class="size-3 text-muted-foreground" />
        </a>
      </template>

      <!-- Operational docs: templates for download, examples for viewing -->
      <template v-else>
        <a
          v-if="doc.template_en"
          :href="getMediaUrl(doc.template_en)"
          :download="downloadFilename(doc.title, 'en')"
          class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
        >
          <Icon name="hugeicons:download-04" class="size-3.5 text-primary" />
          Download Template (EN)
        </a>
        <a
          v-if="doc.template_id"
          :href="getMediaUrl(doc.template_id)"
          :download="downloadFilename(doc.title, 'id')"
          class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
        >
          <Icon name="hugeicons:download-04" class="size-3.5 text-primary" />
          Download Template (ID)
        </a>
      </template>

      <!-- Example file: always open in new tab -->
      <a
        v-if="doc.example_file"
        :href="getMediaUrl(doc.example_file)"
        target="_blank"
        rel="noopener"
        class="inline-flex items-center gap-1.5 rounded-md border border-border bg-card px-2.5 py-1.5 text-xs font-medium tracking-tight transition hover:bg-muted sm:text-sm"
      >
        <Icon name="hugeicons:file-search" class="size-3.5 text-muted-foreground" />
        View Example
        <Icon name="hugeicons:arrow-up-right-01" class="size-3 text-muted-foreground" />
      </a>
    </div>
  </div>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";

const props = defineProps({
  doc: { type: Object, required: true },
  status: { type: String, default: "pending" },
  mode: {
    type: String,
    default: "action",
    validator: (v) => ["view", "action"].includes(v),
  },
});

const typeIcon = computed(() => {
  if (props.doc.document_type === "checkbox_agreement") return "hugeicons:file-validation";
  if (props.doc.document_type === "file_upload") return "hugeicons:file-upload";
  if (props.doc.document_type === "text_input") return "hugeicons:text-font";
  return "hugeicons:file-01";
});

const hasFiles = computed(() => {
  return props.doc.template_en || props.doc.template_id || props.doc.example_file;
});

function getMediaUrl(media) {
  if (!media) return "";
  if (typeof media === "string") return media;
  return media.url || media.original || "";
}

function downloadFilename(title, lang) {
  const slug = title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
  return `${slug}-${lang}.pdf`;
}
</script>
