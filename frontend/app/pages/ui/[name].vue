<template>
  <SidebarProvider :style="{ '--sidebar-width': '18rem' }">
    <UiSidebar :current-name="currentName" />
    <SidebarInset>
      <UiHeader />
      <div class="min-h-screen-offset">
        <div class="container-wider">
          <div class="min-w-0 flex-1">
            <div v-if="!entry" class="my-6 min-w-0 flex-1 sm:p-10">
              <div class="mx-auto max-w-3xl text-center">
                <h1 class="text-primary text-3xl font-semibold tracking-tighter">
                  Component not found
                </h1>
                <p class="text-muted-foreground mt-3 tracking-tight">
                  This component is not in the registry yet.
                </p>
                <Button to="/ui" variant="outline" class="mt-6 tracking-tight">
                  Back to library
                </Button>
              </div>
            </div>

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
                    <div v-for="(example, index) in section.examples" :key="index" class="pt-2">
                      <ComponentPreview :code="example.source" :align="example.align || 'start'">
                        <component :is="example.component" />
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

                      <div v-if="ref.props?.length">
                        <p class="text-muted-foreground mb-2 text-sm tracking-tight">Props</p>
                        <div class="overflow-hidden rounded-xl border">
                          <Table>
                            <TableHeader>
                              <TableRow>
                                <TableHead class="w-[140px]">Prop</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="w-[100px]">Default</TableHead>
                                <TableHead>Description</TableHead>
                              </TableRow>
                            </TableHeader>
                            <TableBody>
                              <TableRow v-for="prop in ref.props" :key="prop.name">
                                <TableCell class="font-mono text-xs sm:text-sm">
                                  {{ prop.name }}
                                </TableCell>
                                <TableCell class="font-mono text-xs">
                                  <span class="text-muted-foreground">{{ prop.type }}</span>
                                </TableCell>
                                <TableCell class="font-mono text-xs">
                                  {{ prop.default }}
                                </TableCell>
                                <TableCell class="text-sm tracking-tight">
                                  {{ prop.description }}
                                </TableCell>
                              </TableRow>
                            </TableBody>
                          </Table>
                        </div>
                      </div>

                      <div v-if="ref.events?.length">
                        <p class="text-muted-foreground mt-3 mb-2 text-sm tracking-tight">Events</p>
                        <div class="overflow-hidden rounded-xl border">
                          <Table>
                            <TableHeader>
                              <TableRow>
                                <TableHead class="w-[180px]">Event</TableHead>
                                <TableHead>Description</TableHead>
                              </TableRow>
                            </TableHeader>
                            <TableBody>
                              <TableRow v-for="event in ref.events" :key="event.name">
                                <TableCell class="font-mono text-xs sm:text-sm">
                                  {{ event.name }}
                                </TableCell>
                                <TableCell class="text-sm tracking-tight">
                                  {{ event.description }}
                                </TableCell>
                              </TableRow>
                            </TableBody>
                          </Table>
                        </div>
                      </div>

                      <div v-if="ref.slots?.length">
                        <p class="text-muted-foreground mt-3 mb-2 text-sm tracking-tight">Slots</p>
                        <div class="overflow-hidden rounded-xl border">
                          <Table>
                            <TableHeader>
                              <TableRow>
                                <TableHead class="w-[180px]">Slot</TableHead>
                                <TableHead>Description</TableHead>
                              </TableRow>
                            </TableHeader>
                            <TableBody>
                              <TableRow v-for="slot in ref.slots" :key="slot.name">
                                <TableCell class="font-mono text-xs sm:text-sm">
                                  {{ slot.name }}
                                </TableCell>
                                <TableCell class="text-sm tracking-tight">
                                  {{ slot.description }}
                                </TableCell>
                              </TableRow>
                            </TableBody>
                          </Table>
                        </div>
                      </div>
                    </div>
                  </section>
                </div>

                <div
                  v-if="prevEntry || nextEntry"
                  class="mx-auto mt-12 flex max-w-3xl items-center justify-between gap-x-2"
                >
                  <Button
                    v-if="prevEntry"
                    :to="`/ui/${prevEntry.name}`"
                    variant="secondary"
                  >
                    <Icon name="lucide:chevron-left" />
                    {{ prevEntry.title }}
                  </Button>
                  <div v-else />

                  <Button
                    v-if="nextEntry"
                    :to="`/ui/${nextEntry.name}`"
                    variant="secondary"
                    class="ml-auto"
                  >
                    {{ nextEntry.title }}
                    <Icon name="lucide:chevron-right" />
                  </Button>
                </div>
              </main>

              <aside
                class="sticky top-[var(--navbar-height-desktop)] hidden h-[calc(100vh-var(--navbar-height-desktop))] w-[220px] shrink-0 overflow-y-auto py-8 xl:block"
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
import ComponentPreview from "@/components/ui-docs/ComponentPreview.vue";
import { getGuide } from "@/components/ui-docs/guides";
import { getEntry } from "@/components/ui-docs/registry";
import { findAdjacent } from "@/components/ui-docs/sidebar-nav";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

definePageMeta({ layout: "empty" });

const route = useRoute();
const currentName = computed(() => route.params.name || "");

const entry = computed(() => {
  const name = currentName.value;
  if (!name) return null;
  return getEntry(name) || getGuide(name) || null;
});

const contentId = computed(() => `ui-${currentName.value}`);

const adjacent = computed(() => findAdjacent(currentName.value));
const prevEntry = computed(() => adjacent.value.prev);
const nextEntry = computed(() => adjacent.value.next);

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
