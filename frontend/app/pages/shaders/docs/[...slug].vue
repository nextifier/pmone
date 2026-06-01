<template>
  <div class="min-w-0 flex-1">
    <DocsNotFound v-if="!entry" />

    <div v-else class="relative flex items-start gap-x-4">
      <main class="mt-6 mb-24 min-w-0 flex-1 sm:p-10 sm:pb-32">
        <div class="mx-auto max-w-3xl">
          <h1
            class="text-primary text-3xl font-semibold tracking-tighter sm:text-4xl lg:text-[2.5rem]"
          >
            {{ entry.title }}
          </h1>
          <p
            v-if="entry.description"
            class="text-muted-foreground mt-3 text-base tracking-tight text-pretty sm:text-lg"
          >
            {{ entry.description }}
          </p>
        </div>

        <div :id="contentId" class="mx-auto mt-10 max-w-3xl scroll-mt-24">
          <MarkdownDoc :source="bodyWithoutTitle" :component-name="componentName" />
        </div>

        <div
          v-if="prev || next"
          class="mx-auto mt-12 flex max-w-3xl items-center justify-between gap-x-2"
        >
          <Button v-if="prev" :to="`/shaders/docs/${prev.name}`" variant="secondary">
            <Icon name="hugeicons:arrow-left-01" />
            {{ prev.title }}
          </Button>
          <div v-else />

          <Button v-if="next" :to="`/shaders/docs/${next.name}`" variant="secondary" class="ml-auto">
            {{ next.title }}
            <Icon name="hugeicons:arrow-right-01" />
          </Button>
        </div>
      </main>

      <aside
        class="sticky top-(--navbar-height-desktop) hidden h-[calc(100vh-var(--navbar-height-desktop))] w-55 shrink-0 overflow-y-auto py-8 xl:block"
      >
        <ScrollSpy
          :key="contentId"
          :content-selector="`#${contentId}`"
          exclude-selector="[role=tabpanel]"
        />
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { getDocsEntry, flatNav } from "@/components/shaders-docs/docs";
import MarkdownDoc from "@/components/shaders-docs/MarkdownDoc.vue";
import DocsNotFound from "@/components/ui-docs/DocsNotFound.vue";
import ScrollSpy from "@/components/ui/scroll-spy/ScrollSpy.vue";

definePageMeta({ layout: "shaders" });

const route = useRoute();
const slug = computed(() =>
  Array.isArray(route.params.slug) ? route.params.slug.join("/") : (route.params.slug ?? ""),
);

const entry = computed(() => getDocsEntry(slug.value));
const componentName = computed(() => (entry.value?.type === "component" ? entry.value.slug : ""));
const contentId = "shaders-doc-content";

const bodyWithoutTitle = computed(() => {
  const body = entry.value?.body ?? "";
  return body.replace(/^#\s+.*$/m, "").replace(/^\s+/, "");
});

const currentIndex = computed(() => flatNav.findIndex((item) => item.name === slug.value));
const prev = computed(() => (currentIndex.value > 0 ? flatNav[currentIndex.value - 1] : null));
const next = computed(() =>
  currentIndex.value >= 0 && currentIndex.value < flatNav.length - 1
    ? flatNav[currentIndex.value + 1]
    : null,
);

usePageMeta(null, {
  title: computed(() => (entry.value ? `${entry.value.title} · Shaders Docs` : "Shaders Docs")),
  description: computed(() => entry.value?.description ?? ""),
});
</script>
