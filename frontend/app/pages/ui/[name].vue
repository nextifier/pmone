<template>
  <SidebarProvider :style="{ '--sidebar-width': '18rem' }">
    <UiSidebar :current-name="currentName" />
    <SidebarInset>
      <UiHeader />
      <div class="min-h-screen-offset">
        <div class="container-wider">
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

                <div :id="contentId" class="mx-auto mt-10 max-w-3xl scroll-mt-24 space-y-12">
                  <section
                    v-if="entry.whenToUse"
                    :id="slugifyId(entry.whenToUse.title)"
                    class="scroll-mt-24 space-y-3"
                  >
                    <h2 class="text-xl font-semibold tracking-tighter sm:text-2xl">
                      {{ entry.whenToUse.title }}
                    </h2>
                    <p
                      class="text-muted-foreground text-base tracking-tight text-pretty sm:text-lg"
                    >
                      {{ entry.whenToUse.description }}
                    </p>
                  </section>

                  <section
                    v-for="section in entry.sections"
                    :key="section.id"
                    :id="section.id"
                    class="scroll-mt-24 space-y-3"
                  >
                    <h2 class="text-xl font-semibold tracking-tighter sm:text-2xl">
                      {{ section.title }}
                    </h2>
                    <p
                      v-if="section.description"
                      class="text-muted-foreground text-base tracking-tight text-pretty sm:text-lg"
                    >
                      {{ section.description }}
                    </p>
                    <div v-for="exampleId in section.examples" :key="exampleId" class="pt-2">
                      <ComponentPreview
                        v-if="resolveExample(exampleId).component"
                        :code="resolveExample(exampleId).source"
                        :align="section.align || 'start'"
                      >
                        <component :is="resolveExample(exampleId).component" />
                      </ComponentPreview>
                    </div>
                  </section>

                  <section
                    v-if="entry.apiReference?.length"
                    id="api-reference"
                    class="scroll-mt-24 space-y-6"
                  >
                    <div class="space-y-2">
                      <h2 class="text-xl font-semibold tracking-tighter sm:text-2xl">
                        API Reference
                      </h2>
                      <p class="text-muted-foreground text-base tracking-tight sm:text-lg">
                        Props, events, and slots for each sub-component.
                      </p>
                    </div>

                    <div v-for="ref in entry.apiReference" :key="ref.component" class="space-y-3">
                      <h3 class="font-mono text-base tracking-tight">{{ ref.component }}</h3>

                      <ApiReferenceTable label="Props" :columns="propColumns" :rows="ref.props" />
                      <ApiReferenceTable
                        label="Events"
                        :columns="eventColumns"
                        :rows="ref.events"
                      />
                      <ApiReferenceTable label="Slots" :columns="slotColumns" :rows="ref.slots" />
                    </div>
                  </section>
                </div>

                <DocsPrevNext :prev="prevEntry" :next="nextEntry" />
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
        </div>
      </div>
    </SidebarInset>
  </SidebarProvider>
</template>

<script setup>
import ApiReferenceTable from "@/components/ui-docs/ApiReferenceTable.vue";
import ComponentPreview from "@/components/ui-docs/ComponentPreview.vue";
import DocsNotFound from "@/components/ui-docs/DocsNotFound.vue";
import DocsPrevNext from "@/components/ui-docs/DocsPrevNext.vue";
import { getExample } from "@/components/ui-docs/examples-loader";
import { getDocsEntry } from "@/components/ui-docs/lookup";
import { findAdjacent } from "@/components/ui-docs/sidebar-nav";

definePageMeta({ layout: "empty" });

const route = useRoute();
const currentName = computed(() => route.params.name || "");

const entry = computed(() => (currentName.value ? getDocsEntry(currentName.value) : null));

const contentId = computed(() => `ui-${currentName.value}`);

const adjacent = computed(() => findAdjacent(currentName.value));
const prevEntry = computed(() => adjacent.value.prev);
const nextEntry = computed(() => adjacent.value.next);

const propColumns = [
  { key: "name", label: "Prop", width: "140px", mono: true },
  { key: "type", label: "Type", monoSmall: true },
  { key: "default", label: "Default", width: "100px", monoSmall: true },
  { key: "description", label: "Description" },
];

const eventColumns = [
  { key: "name", label: "Event", width: "180px", mono: true },
  { key: "description", label: "Description" },
];

const slotColumns = [
  { key: "name", label: "Slot", width: "180px", mono: true },
  { key: "description", label: "Description" },
];

function resolveExample(exampleId) {
  return getExample(currentName.value, exampleId);
}

usePageMeta(null, {
  title: computed(() => (entry.value ? `${entry.value.title} · UI Library` : "UI Library")),
  description: computed(() => entry.value?.description || ""),
});

function slugifyId(text) {
  return text
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^\w-]+/g, "");
}
</script>
