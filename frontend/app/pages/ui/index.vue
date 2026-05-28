<template>
  <div class="bg-background min-h-screen">
    <header
      class="bg-background/80 sticky top-0 z-50 border-b backdrop-blur"
    >
      <div class="mx-auto flex h-14 max-w-7xl items-center gap-x-3 px-4 sm:px-6 lg:px-8">
        <NuxtLink to="/ui" class="flex items-center gap-x-2">
          <div
            class="bg-sidebar-primary text-sidebar-primary-foreground squircle flex aspect-square size-8 items-center justify-center rounded-lg"
          >
            <LogoMark class="text-primary-foreground size-4" />
          </div>
          <span class="text-base font-semibold tracking-tight">UI Library</span>
        </NuxtLink>

        <div class="ml-auto flex items-center gap-x-2">
          <UiSearch />
          <ColorModeToggle />
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 pt-12 pb-24 sm:px-6 sm:pt-20 lg:px-8 lg:pt-24">
      <div class="max-w-3xl">
        <h1
          class="text-primary text-4xl font-semibold tracking-tighter sm:text-5xl lg:text-[3.5rem]"
        >
          A modern Vue component library.
        </h1>
        <p class="text-muted-foreground mt-4 text-base tracking-tight text-pretty sm:text-lg">
          Components used across the admin and marketing sites. Built on reka-ui, styled with
          Tailwind, and shipped with a few project-specific additions.
        </p>

        <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2">
          <Button to="/ui/introduction" size="lg">
            Get started
            <Icon name="lucide:arrow-right" />
          </Button>
          <Button to="/ui/badge" size="lg" variant="outline">Browse components</Button>
        </div>
      </div>

      <div
        class="grid gap-3 pt-12 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      >
        <NuxtLink
          v-for="item in items"
          :key="item.name"
          :to="`/ui/${item.name}`"
          data-slot="card-frame"
          class="bg-card text-card-foreground relative flex flex-col rounded-2xl border shadow-xs/5 not-dark:bg-clip-padding before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:bg-muted/72 before:shadow-[0_1px_--theme(--color-black/4%)] dark:before:shadow-[0_-1px_--theme(--color-white/6%)] [--radius-2xl:1rem]"
        >
          <div
            data-slot="card-frame-header"
            class="relative z-10 grid auto-rows-min grid-rows-[auto_1fr] items-start gap-x-4 px-6 py-4"
          >
            <h2 class="self-center text-base font-semibold tracking-tight">{{ item.title }}</h2>
            <p
              class="text-muted-foreground line-clamp-2 self-center text-sm tracking-tight sm:h-[2lh]"
            >
              {{ item.description }}
            </p>
          </div>

          <div
            data-slot="card"
            class="text-card-foreground relative flex min-h-55 flex-1 flex-col flex-wrap overflow-x-auto rounded-2xl border shadow-xs/5 not-dark:bg-clip-padding bg-[color-mix(in_srgb,var(--color-card),var(--color-sidebar))] dark:bg-background -m-px before:pointer-events-none before:absolute before:inset-0 before:rounded-[calc(var(--radius-2xl)-1px)] before:shadow-[0_1px_--theme(--color-black/4%)] dark:before:shadow-[0_-1px_--theme(--color-white/6%)] pointer-events-none"
          >
            <div
              data-slot="card-panel"
              class="relative z-10 flex flex-1 items-center justify-center p-6 [--border:--alpha(var(--color-black)/7%)] [--btn-from:--alpha(var(--color-primary)/90%)] [--btn-to:var(--color-primary)] dark:[--border:--alpha(var(--color-white)/3%)] dark:[--btn-from:var(--color-primary)] dark:[--btn-to:--alpha(var(--color-primary)/90%)]"
            >
              <component v-if="item.illustration" :is="item.illustration" />
            </div>
          </div>
        </NuxtLink>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ColorModeToggle } from "@/components/ui/color-mode-toggle";
import { flatNav } from "@/components/ui-docs/sidebar-nav";
import { getEntry } from "@/components/ui-docs/registry";
import { getIllustration } from "@/components/ui-docs/illustrations";

definePageMeta({ layout: "empty" });

// useState makes the metadata list survive Nuxt SSR → client hydration even if
// Vite's dev SSR module cache is out of sync with the client (which happens
// when registry files are added/removed without a dev-server restart). The
// server serialises the list once; the client reads it from payload instead
// of re-evaluating Object.keys(registry).
const itemMeta = useState("ui-cards", () =>
  flatNav
    .filter((item) => item.group === "Components")
    .map((item) => {
      const entry = getEntry(item.name);
      return {
        name: item.name,
        title: item.title,
        description: entry?.description || "",
      };
    }),
);

// Illustration is a Vue component (not serialisable), resolved per-render.
// If a stale cache means the illustration isn't found, the slot stays empty
// without breaking hydration.
const items = computed(() =>
  itemMeta.value.map((item) => ({
    ...item,
    illustration: getIllustration(item.name),
  })),
);

usePageMeta(null, {
  title: "UI Library",
  description: "A modern Vue component library with examples, variants, and code snippets.",
});
</script>
