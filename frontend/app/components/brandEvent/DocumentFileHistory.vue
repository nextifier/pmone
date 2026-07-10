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
      <div v-for="group in history" :key="group.field_ulid" class="space-y-1.5">
        <div
          v-for="version in group.versions"
          :key="version.id"
          class="flex items-center gap-x-2"
        >
          <Badge :variant="version.is_current ? 'success' : 'muted'" class="shrink-0">
            {{ version.is_current ? "Current" : `v${version.version}` }}
          </Badge>
          <a
            :href="version.url"
            target="_blank"
            rel="noopener"
            class="text-primary min-w-0 truncate text-sm tracking-tight hover:underline"
          >
            {{ version.name }}
          </a>
          <span class="text-muted-foreground shrink-0 text-sm tracking-tight">
            <span v-if="version.size">{{ formatSize(version.size) }} · </span>
            <span v-if="version.uploaded_by_name">{{ version.uploaded_by_name }} · </span>
            {{ formatDate(version.uploaded_at) }}
          </span>
        </div>
      </div>
    </CollapsibleContent>
  </Collapsible>
</template>

<script setup>
import { Badge } from "@/components/ui/badge";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";

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

function formatSize(bytes) {
  if (!bytes || bytes <= 0) return "";
  const units = ["B", "KB", "MB", "GB"];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function formatDate(value) {
  return value ? $dayjs(value).format("MMM D, YYYY [at] h:mm A") : "-";
}
</script>
