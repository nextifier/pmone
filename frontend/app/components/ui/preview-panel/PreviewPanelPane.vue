<script setup lang="ts">
/**
 * One half of the panel. `force-mount` keeps both panes in the DOM — reka's
 * TabsContent never hides itself under force-mount, so the stacked/split
 * decision is authored entirely in CSS and there is no hydration flash.
 */
import { cn } from "@/lib/utils";
import { TabsContent } from "reka-ui";
import { computed, inject, type HTMLAttributes } from "vue";
import {
  PREVIEW_PANEL_CONTEXT,
  PREVIEW_PANEL_FALLBACK_SPLIT,
  type PreviewPanelTab,
} from "./context";

const props = withDefaults(
  defineProps<{
    value: PreviewPanelTab;
    /** flex-grow factor once the panes sit side by side. */
    grow?: number;
    /**
     * Own the scroller. Set false when the pane's child already scrolls itself
     * (the preview surface does).
     */
    scroll?: boolean;
    /**
     * Bleed a 4px gutter outside the pane and pad it back inside the scroller.
     * `overflow-y: auto` computes `overflow-x` to `auto` too, so a focus ring
     * (`ring-2 ring-offset-2`) on a full-width input would otherwise trip a
     * horizontal scrollbar. Compensating on the outside keeps the content
     * flush with the page header instead of inset from it.
     */
    gutter?: boolean;
    class?: HTMLAttributes["class"];
    scrollClass?: HTMLAttributes["class"];
  }>(),
  { grow: 1, scroll: true, gutter: false },
);

const ctx = inject(PREVIEW_PANEL_CONTEXT, null);
const split = computed(() => ctx?.split.value ?? PREVIEW_PANEL_FALLBACK_SPLIT);
</script>

<template>
  <TabsContent
    :value="props.value"
    force-mount
    as="div"
    data-slot="preview-panel-pane"
    :class="
      cn(
        'relative flex min-h-0 min-w-0 basis-auto flex-col outline-none',
        props.gutter && '-mx-1',
        split.stackHide,
        split.paneBasis,
        props.class,
      )
    "
    :style="{ flexGrow: props.grow }"
  >
    <!--
      `overflow-y-auto` is unconditional on purpose. Where the panel has no
      definite height this box simply grows to its content and the document
      scrolls; a scroll container with zero scroll range clips nothing. That is
      what lets the same classes serve the locked and unlocked shells.
      `scrollbar-gutter` keeps content from shifting 5px the moment a form grows
      past the fold.
    -->
    <div
      v-if="props.scroll"
      data-slot="preview-panel-scroll"
      :class="
        cn(
          'min-h-0 flex-1 overflow-y-auto pb-6 [scrollbar-gutter:stable]',
          props.gutter && 'px-1',
          split.overscroll,
          split.stackClear,
          props.scrollClass,
        )
      "
    >
      <slot />
    </div>
    <slot v-else />
  </TabsContent>
</template>
