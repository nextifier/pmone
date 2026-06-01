<script setup>
import { ref, computed } from "vue";
import { refDebounced } from "@vueuse/core";
import { useShaderPresets } from "@/components/shaders-docs/useShaderPresets";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Badge } from "@/components/ui/badge";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import PresetCard from "@/components/shaders/PresetCard.vue";

definePageMeta({ layout: "empty" });

usePageMeta(null, {
  title: "Shaders",
  description: "A gallery of GPU-accelerated shader presets with a live editor, ready to drop into any project.",
});

const { index, collections } = useShaderPresets();

const query = ref("");
const debouncedQuery = refDebounced(query, 200);
const activeCategory = ref("all");
const activeCollection = ref("all");
const collectionOpen = ref(false);

const sortedCollections = computed(() =>
  [...collections].sort((a, b) => a.name.localeCompare(b.name)),
);

const activeCollectionName = computed(() =>
  activeCollection.value === "all"
    ? "All collections"
    : (collections.find((c) => c.id === activeCollection.value)?.name ?? "All collections"),
);

function pickCollection(id) {
  activeCollection.value = id;
  collectionOpen.value = false;
}

const hasActiveFilters = computed(
  () =>
    Boolean(debouncedQuery.value.trim()) ||
    activeCategory.value !== "all" ||
    activeCollection.value !== "all",
);

function clearFilters() {
  query.value = "";
  activeCategory.value = "all";
  activeCollection.value = "all";
}

const formatCategory = (c) =>
  c === "all" ? "All" : c.replace(/-/g, " ").replace(/\b\w/g, (m) => m.toUpperCase());

const categories = computed(() => {
  const set = new Set(index.map((p) => p.category).filter(Boolean));
  return ["all", ...[...set].sort()];
});

const filtered = computed(() => {
  const q = debouncedQuery.value.trim().toLowerCase();
  return index.filter((preset) => {
    if (activeCategory.value !== "all" && preset.category !== activeCategory.value) return false;
    if (activeCollection.value !== "all" && preset.collectionId !== activeCollection.value)
      return false;
    if (!q) return true;
    return (
      preset.title.toLowerCase().includes(q) ||
      (preset.description ?? "").toLowerCase().includes(q) ||
      (preset.category ?? "").toLowerCase().includes(q)
    );
  });
});

const SORT_OPTIONS = [
  { value: "curated", label: "Curated" },
  { value: "name-asc", label: "Name A-Z" },
  { value: "name-desc", label: "Name Z-A" },
];
const sortBy = ref("curated");

const sorted = computed(() => {
  const list = filtered.value;
  if (sortBy.value === "name-asc") {
    return [...list].sort((a, b) => a.title.localeCompare(b.title));
  }
  if (sortBy.value === "name-desc") {
    return [...list].sort((a, b) => b.title.localeCompare(a.title));
  }
  return list;
});
</script>

<template>
  <div class="bg-background min-h-screen">
    <ShadersHeader />

    <main class="container-wider pt-12 pb-24 sm:pt-16">
      <div class="max-w-3xl">
        <h1 class="text-primary text-4xl font-semibold tracking-tighter sm:text-5xl">
          GPU shaders, ready to ship.
        </h1>
        <p class="text-muted-foreground mt-4 text-base tracking-tight text-pretty sm:text-lg">
          A curated gallery of {{ index.length }} declarative WebGPU presets. Preview them live,
          tweak in the editor, and export to Vue code or an image.
        </p>
        <div class="mt-6 flex flex-wrap items-center gap-x-3 gap-y-2">
          <Button to="/shaders/editor" size="lg">
            Open editor
            <Icon name="hugeicons:arrow-right-01" />
          </Button>
          <Button to="/shaders/docs" size="lg" variant="outline">Read the docs</Button>
        </div>
      </div>

      <div class="mt-12 flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
          <InputGroup class="max-w-sm flex-1">
            <InputGroupAddon>
              <Icon name="hugeicons:search-01" />
            </InputGroupAddon>
            <InputGroupInput v-model="query" placeholder="Search presets…" />
          </InputGroup>

          <Popover v-model:open="collectionOpen">
            <PopoverTrigger as-child>
              <Button variant="outline" class="w-56 justify-between">
                <span class="truncate">{{ activeCollectionName }}</span>
                <Icon name="hugeicons:unfold-more" class="text-muted-foreground shrink-0" />
              </Button>
            </PopoverTrigger>
            <PopoverContent align="end" class="w-64 p-0">
              <Command>
                <CommandInput placeholder="Search collections…" />
                <CommandList class="max-h-72">
                  <CommandEmpty>No collection.</CommandEmpty>
                  <CommandGroup>
                    <CommandItem value="All collections" class="tracking-tight" @select="pickCollection('all')">
                      All collections
                      <Icon
                        v-if="activeCollection === 'all'"
                        name="hugeicons:tick-02"
                        class="ml-auto shrink-0"
                      />
                    </CommandItem>
                    <CommandItem
                      v-for="collection in sortedCollections"
                      :key="collection.id"
                      :value="collection.name"
                      class="tracking-tight"
                      @select="pickCollection(collection.id)"
                    >
                      <span class="truncate">{{ collection.name }}</span>
                      <span class="text-muted-foreground ml-auto shrink-0 pl-2 text-xs">
                        {{ collection.presetCount }}
                      </span>
                    </CommandItem>
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>

          <Select v-model="sortBy">
            <SelectTrigger class="w-40">
              <Icon name="hugeicons:arrow-up-down" class="text-muted-foreground size-4" />
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="opt in SORT_OPTIONS" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="flex flex-wrap gap-1.5">
          <Button
            v-for="category in categories"
            :key="category"
            size="sm"
            :variant="activeCategory === category ? 'default' : 'outline'"
            @click="activeCategory = category"
          >
            {{ formatCategory(category) }}
          </Button>
        </div>

        <div v-if="hasActiveFilters" class="flex flex-wrap items-center gap-2">
          <Badge v-if="activeCategory !== 'all'" variant="secondary" class="gap-x-1 py-1 pr-1 pl-2">
            {{ formatCategory(activeCategory) }}
            <button
              class="hover:bg-background/80 rounded-full p-0.5 transition"
              aria-label="Clear category filter"
              @click="activeCategory = 'all'"
            >
              <Icon name="hugeicons:cancel-01" class="size-3" />
            </button>
          </Badge>

          <Badge
            v-if="activeCollection !== 'all'"
            variant="secondary"
            class="gap-x-1 py-1 pr-1 pl-2"
          >
            {{ activeCollectionName }}
            <button
              class="hover:bg-background/80 rounded-full p-0.5 transition"
              aria-label="Clear collection filter"
              @click="activeCollection = 'all'"
            >
              <Icon name="hugeicons:cancel-01" class="size-3" />
            </button>
          </Badge>

          <Badge
            v-if="debouncedQuery.trim()"
            variant="secondary"
            class="gap-x-1 py-1 pr-1 pl-2"
          >
            “{{ debouncedQuery.trim() }}”
            <button
              class="hover:bg-background/80 rounded-full p-0.5 transition"
              aria-label="Clear search"
              @click="query = ''"
            >
              <Icon name="hugeicons:cancel-01" class="size-3" />
            </button>
          </Badge>

          <Button
            variant="ghost"
            size="sm"
            class="text-muted-foreground h-auto px-2 py-1"
            @click="clearFilters"
          >
            Clear all
          </Button>
        </div>
      </div>

      <p class="text-muted-foreground mt-6 text-sm tracking-tight">
        {{ sorted.length }} {{ sorted.length === 1 ? "preset" : "presets" }}
      </p>

      <div class="mt-4 grid gap-x-2 gap-y-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <PresetCard
          v-for="preset in sorted"
          :key="preset.id"
          :preset="preset"
        />
      </div>

      <div
        v-if="!sorted.length"
        class="text-muted-foreground py-16 text-center text-sm tracking-tight"
      >
        No presets match your search.
      </div>
    </main>
  </div>
</template>
