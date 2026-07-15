<template>
  <Attachment v-if="normalized" :size="size" :orientation="orientation" :state="state">
    <AttachmentMedia :variant="normalized.isImage ? 'image' : 'icon'">
      <slot name="media" :file="normalized">
        <img v-if="normalized.isImage" :src="normalized.url" :alt="displayName" />
        <Icon v-else :name="normalized.icon" class="size-5" />
      </slot>
    </AttachmentMedia>

    <AttachmentContent>
      <AttachmentTitle>{{ displayName }}</AttachmentTitle>
      <AttachmentDescription v-if="$slots.description || description">
        <slot name="description" :file="normalized" :description="description">{{ description }}</slot>
      </AttachmentDescription>
    </AttachmentContent>

    <AttachmentActions v-if="$slots.actions">
      <slot name="actions" :file="normalized" />
    </AttachmentActions>

    <AttachmentTrigger
      v-if="normalized.url && !disableOpen"
      as="a"
      :href="normalized.url"
      target="_blank"
      rel="noopener"
      :aria-label="`Open ${displayName}`"
    />
  </Attachment>
</template>

<script setup>
import { computed } from "vue";
import {
  Attachment,
  AttachmentActions,
  AttachmentContent,
  AttachmentDescription,
  AttachmentMedia,
  AttachmentTitle,
  AttachmentTrigger,
} from "@/components/ui/attachment";
import { formatFileSize, normalizeAttachment } from "@/utils/attachments";

const props = defineProps({
  // A media reference: a URL string, or an object with url/original + name/file_name/alt + size/mime_type.
  file: { type: [Object, String], default: null },
  // Forces the displayed filename (e.g. a static "Download voucher" label).
  label: { type: String, default: "" },
  fallbackName: { type: String, default: "Attachment" },
  size: { type: String, default: "default" },
  orientation: { type: String, default: "horizontal" },
  state: { type: String, default: "done" },
  // Hides the open-in-new-tab trigger (for staged/not-yet-openable files).
  disableOpen: { type: Boolean, default: false },
});

const normalized = computed(() => normalizeAttachment(props.file, { fallbackName: props.fallbackName }));

const displayName = computed(() => props.label || normalized.value?.name || props.fallbackName);

const description = computed(() => {
  if (!normalized.value) return "";
  const ext = normalized.value.extension ? normalized.value.extension.toUpperCase() : "";
  return [ext, formatFileSize(normalized.value.size)].filter(Boolean).join(" · ");
});
</script>
