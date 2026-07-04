<script setup lang="ts">
// Direct-render showcase panel (no iframe): renders the ported shadcn-vue
// create preview blocks and lets the appearance tokens/style (driven by
// useAppearance) theme them live. Adapted from shadcn-vue apps/v4 (MIT) via
// levenium. The 01/02 switcher swaps the two blocks.
import PreviewBlock from "./preview/page.vue";
import PreviewBlock02 from "./preview-02/page.vue";
import { useDesignSystemSearchParams } from "@/composables/useDesignSystemSearchParams";

const params = useDesignSystemSearchParams();

// Match shadcn /create: 01 = preview-02 (default), 02 = preview.
const PREVIEW_ITEMS = [
  { label: "01", value: "preview-02" },
  { label: "02", value: "preview" },
];
</script>

<template>
  <div
    class="relative flex min-h-0 min-w-0 flex-1 flex-col justify-center overflow-hidden rounded-2xl ring ring-foreground/10 md:ring-muted dark:ring-foreground/10"
  >
    <div class="relative z-0 mx-auto flex w-full min-h-0 flex-1 flex-col overflow-hidden">
      <div class="absolute inset-0 bg-muted dark:bg-muted/30" />
      <!-- Scroll container (iframe replacement): fills the panel and scrolls on
           every breakpoint, including mobile. -->
      <div class="relative z-10 size-full flex-1 overflow-auto overscroll-contain">
        <PreviewBlock v-if="params.item.value !== 'preview-02'" />
        <PreviewBlock02 v-else />
      </div>
    </div>

    <!-- Preview switcher — forced-dark, translucent, blurred pill (bottom-right). -->
    <div
      class="dark absolute right-3 bottom-3 z-20 flex items-center gap-1 rounded-xl bg-card/90 p-1 shadow-xl backdrop-blur-xl"
    >
      <button
        v-for="item in PREVIEW_ITEMS"
        :key="item.value"
        type="button"
        :aria-label="`Show preview block ${item.label}`"
        :data-active="params.item.value === item.value"
        class="text-muted-foreground inline-flex h-7 min-w-8 cursor-pointer items-center justify-center rounded-lg px-2.5 text-xs font-medium tracking-tight tabular-nums hover:text-foreground motion-safe:transition-colors data-[active=true]:bg-accent data-[active=true]:text-accent-foreground"
        @click="params.item.value = item.value"
      >
        {{ item.label }}
      </button>
    </div>
  </div>
</template>
