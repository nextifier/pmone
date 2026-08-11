<script setup lang="ts">
/**
 * The floating Edit/Preview pill. Consolidates the hand-rolled copies that had
 * drifted across the brand editors and `post/TabsTrigger.vue`, so `items` and
 * `size` cover every call shape.
 *
 * Reads reka's Tabs context from any ancestor `TabsRoot`, which is why it works
 * both inside `PreviewPanel` and inside a page that owns its own tabs.
 */
import { cn } from "@/lib/utils";
import { TabsIndicator, TabsList, TabsTrigger } from "reka-ui";
import type { HTMLAttributes } from "vue";
import {
  previewPanelSwitcherSizeClasses,
  type PreviewPanelSwitcherItem,
  type PreviewPanelSwitcherSize,
} from "./context";

const props = withDefaults(
  defineProps<{
    items: PreviewPanelSwitcherItem[];
    size?: PreviewPanelSwitcherSize;
    class?: HTMLAttributes["class"];
  }>(),
  { size: "md" },
);
</script>

<template>
  <TabsList
    data-slot="preview-panel-switcher"
    :class="
      cn(
        'bg-muted text-body relative isolate inline-flex items-center justify-center rounded-full border p-0.5 shadow-lg',
        props.class,
      )
    "
  >
    <TabsIndicator
      class="absolute inset-y-0.5 left-0 z-0 w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) rounded-full transition-[translate,width] duration-(--tabs-dur) ease-(--tabs-ease)"
    >
      <div class="bg-primary size-full rounded-full" />
    </TabsIndicator>

    <TabsTrigger
      v-for="item in props.items"
      :key="item.value"
      :value="item.value"
      :class="
        cn(
          'data-[state=active]:text-primary-foreground ring-offset-background focus-visible:ring-ring relative z-10 inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-full font-medium tracking-tight whitespace-nowrap transition-colors outline-hidden select-none focus-visible:ring-2 focus-visible:ring-offset-2',
          previewPanelSwitcherSizeClasses[props.size],
        )
      "
    >
      <Icon v-if="item.icon" :name="item.icon" class="size-4 shrink-0" />
      <span>{{ item.label }}</span>
    </TabsTrigger>
  </TabsList>
</template>
