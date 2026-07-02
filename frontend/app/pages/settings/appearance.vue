<template>
  <div class="flex h-[calc(100svh-var(--navbar-height-desktop,3.5rem))] min-w-0 flex-col overflow-hidden py-4">
    <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4 lg:flex-row">
      <!-- Controls (left) — the Customizer. Uses `border` (not `ring`): a ring
           paints OUTSIDE the box and its left edge would be clipped by the
           container's `overflow-hidden`; a border sits inside the box. -->
      <div class="bg-card border-border shrink-0 overflow-y-auto rounded-2xl border p-4 lg:w-72">
        <AppearanceCustomizer />
      </div>

      <!-- Preview (right) — clearly labelled so it reads as a live preview of
           the chosen style, not editable settings. `min-w-0` lets this column
           shrink below the (very wide) showcase grid so the horizontal scroll
           is contained INSIDE the preview panel, not the whole page. -->
      <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-3 lg:w-0">
        <div class="min-w-0">
          <h2 class="text-foreground text-base font-medium tracking-tight">Live Preview</h2>
          <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
            A live preview of the components in your selected style. Changes apply instantly, no reload.
          </p>
        </div>

        <!-- Client-only: the showcase is a live preview (not SEO content) and its
             ~70 reka-ui components would otherwise cause SSR/client `useId`
             hydration drift. Rendering it only on the client removes the mismatch
             and keeps SSR light. -->
        <ClientOnly>
          <AppearancePreviewPanel class="min-h-[60svh] lg:min-h-0" />
          <template #fallback>
            <div class="bg-muted dark:bg-background min-h-[60svh] flex-1 rounded-2xl lg:min-h-0" />
          </template>
        </ClientOnly>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppearanceCustomizer from "@/components/appearance/AppearanceCustomizer.vue";
import AppearancePreviewPanel from "@/components/appearance/showcase/PreviewPanel.vue";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, { title: "Appearance" });
</script>
