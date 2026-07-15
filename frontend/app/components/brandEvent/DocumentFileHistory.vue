<template>
  <Collapsible v-if="totalVersions > 1" v-model:open="open" class="mt-2">
    <CollapsibleTrigger
      class="text-muted-foreground hover:text-foreground flex items-center gap-1 text-sm tracking-tight transition"
    >
      <Icon
        name="hugeicons:clock-01"
        class="size-3.5 shrink-0"
      />
      File history ({{ totalVersions }} versions)
      <Icon
        name="hugeicons:arrow-down-01"
        :class="['size-3.5 shrink-0 transition-transform', open && 'rotate-180']"
      />
    </CollapsibleTrigger>

    <CollapsibleContent class="mt-2 space-y-3">
      <div v-for="group in history" :key="group.field_ulid" class="space-y-2">
        <div
          v-for="version in group.versions"
          :key="version.id"
          class="flex items-center gap-x-2"
        >
          <Badge :variant="version.is_current ? 'success' : 'muted'" class="shrink-0">
            {{ version.is_current ? "Current" : `v${version.version}` }}
          </Badge>
          <AttachmentLink size="sm" :file="version" fallback-name="File">
            <template #description>
              <span v-if="version.size">{{ formatFileSize(version.size) }} · </span>
              <span v-if="version.uploaded_by_name">{{ version.uploaded_by_name }} · </span>
              {{ formatDate(version.uploaded_at) }}
            </template>
          </AttachmentLink>
        </div>
      </div>
    </CollapsibleContent>
  </Collapsible>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { formatFileSize } from "@/utils/attachments";

const { $dayjs } = useNuxtApp();

const props = defineProps({
  history: {
    type: Array,
    default: () => [],
  },
});

const open = ref(false);

const totalVersions = computed(() =>
  props.history.reduce((sum, group) => sum + (group.versions?.length || 0), 0)
);

function formatDate(value) {
  return value ? $dayjs(value).format("MMM D, YYYY [at] h:mm A") : "-";
}
</script>
