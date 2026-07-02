<script setup lang="ts">
// Direct-render showcase panel (no iframe): renders the ported shadcn-vue
// create preview blocks and lets the appearance tokens/style (driven by
// useAppearance) theme them live. Adapted from shadcn-vue apps/v4 (MIT) via
// levenium. The 01/02 switcher swaps the two blocks.
import PreviewBlock from "./preview/page.vue";
import PreviewBlock02 from "./preview-02/page.vue";
import { useDesignSystemSearchParams } from "@/composables/useDesignSystemSearchParams";

const params = useDesignSystemSearchParams();
</script>

<template>
  <div class="bg-muted dark:bg-background relative min-h-0 flex-1 overflow-hidden rounded-2xl">
    <div class="h-full overflow-auto">
      <PreviewBlock v-if="params.item.value !== 'preview-02'" />
      <PreviewBlock02 v-else />
    </div>

    <!-- Preview switcher — segmented Tabs look (bg-muted track, active raised
         bg-background + shadow), matching TabsList/TabsTrigger. -->
    <div
      class="bg-muted text-muted-foreground absolute right-4 bottom-4 z-50 inline-flex h-8 items-center justify-center rounded-lg p-[3px] shadow-sm"
    >
      <button
        type="button"
        aria-label="Show preview block 1"
        class="inline-flex h-full items-center justify-center rounded-md px-2.5 text-xs font-medium tracking-tight tabular-nums transition-[color,box-shadow]"
        :class="params.item.value !== 'preview-02' ? 'bg-background text-foreground shadow-sm' : 'hover:text-foreground'"
        @click="params.item.value = 'preview'"
      >
        01
      </button>
      <button
        type="button"
        aria-label="Show preview block 2"
        class="inline-flex h-full items-center justify-center rounded-md px-2.5 text-xs font-medium tracking-tight tabular-nums transition-[color,box-shadow]"
        :class="params.item.value === 'preview-02' ? 'bg-background text-foreground shadow-sm' : 'hover:text-foreground'"
        @click="params.item.value = 'preview-02'"
      >
        02
      </button>
    </div>
  </div>
</template>
