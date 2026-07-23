<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { cn } from "@/lib/utils";
import ScrollSpyItems from "./ScrollSpyItems.vue";
import ScrollSpyScrollArea from "./ScrollSpyScrollArea.vue";
import { useScrollSpy, type ScrollSpyHeading } from "./useScrollSpy";

const props = withDefaults(
  defineProps<{
    /** CSS selector of the container whose `h2`–`h6` become the entries. */
    contentSelector: string;
    /** Skip headings whose closest match this selector, e.g. `[role=tabpanel]`. */
    excludeSelector?: string;
    showLabel?: boolean;
    label?: string;
    class?: HTMLAttributes["class"];
  }>(),
  { excludeSelector: "", showLabel: true, label: "On this page" },
);

const emit = defineEmits<{
  "headings-found": [headings: ScrollSpyHeading[]];
}>();

const { headings, activeIds, activeAnchorId, scrollToHeading } = useScrollSpy({
  contentSelector: () => props.contentSelector,
  excludeSelector: () => props.excludeSelector,
  onHeadings: (found) => emit("headings-found", found),
});
</script>

<template>
  <nav
    v-show="headings.length > 0"
    data-slot="scroll-spy"
    :class="cn('flex min-h-0 flex-col', props.class)"
  >
    <h3
      v-if="showLabel"
      class="text-muted-foreground inline-flex items-center gap-1.5 text-sm"
    >
      <Icon name="lucide:text-align-start" class="size-4 shrink-0" />
      {{ label }}
    </h3>

    <ScrollSpyScrollArea>
      <ScrollSpyItems
        :headings="headings"
        :active-ids="activeIds"
        :anchor-id="activeAnchorId"
        @select="scrollToHeading"
      />
    </ScrollSpyScrollArea>
  </nav>
</template>
