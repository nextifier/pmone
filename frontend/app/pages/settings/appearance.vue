<template>
  <!-- Full-bleed designer surface (cancels the app layout's px-4), fixed to the
       viewport minus the navbar so the two panels fill the screen with no page
       scroll — mirrors shadcn /create. `--customizer-width`/`--gap` drive the
       fixed customizer column + the padding/gap, exactly like upstream. -->
  <div
    class="relative z-10 -mx-4 flex h-[calc(100svh-var(--navbar-height-mobile))] min-h-0 flex-col overflow-hidden [--customizer-width:--spacing(48)] [--gap:--spacing(4)] md:[--gap:--spacing(6)] lg:h-[calc(100svh-var(--navbar-height-desktop))] 2xl:[--customizer-width:--spacing(56)]"
  >
    <div
      class="flex min-h-0 flex-1 flex-col gap-(--gap) p-(--gap) pt-[calc(var(--gap)*0.5)] md:flex-row-reverse"
    >
      <!-- Preview (top on mobile / right on desktop). Client-only: the showcase is
           a live preview (not SEO content) and its ~70 reka-ui components would
           otherwise cause SSR/client `useId` hydration drift. -->
      <ClientOnly>
        <AppearancePreviewPanel />
        <template #fallback>
          <div
            class="bg-muted dark:bg-muted/30 min-h-[50svh] flex-1 rounded-2xl ring ring-foreground/10 md:min-h-0 md:ring-muted dark:ring-foreground/10"
          />
        </template>
      </ClientOnly>

      <!-- Customizer (bottom on mobile / left column on desktop). -->
      <AppearanceCustomizer />
    </div>
  </div>
</template>

<script setup lang="ts">
import AppearanceCustomizer from "@/components/appearance/AppearanceCustomizer.vue";
import AppearancePreviewPanel from "@/components/appearance/showcase/PreviewPanel.vue";

definePageMeta({
  middleware: ["sanctum:auth"],
  layout: "app",
});

usePageMeta(null, { title: "Appearance" });
</script>
