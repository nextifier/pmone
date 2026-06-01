<template>
  <div>
    <button
      class="border-border bg-card hover:bg-muted text-muted-foreground flex h-8 w-full max-w-xs items-center gap-x-2 justify-self-end rounded-lg border px-2.5 text-sm tracking-tight transition"
      @click="open = true"
    >
      <Icon name="hugeicons:search-01" class="size-4 shrink-0" />
      <span class="truncate">Search presets</span>
      <KbdGroup class="ml-auto hidden shrink-0 sm:inline-flex">
        <Kbd>{{ metaSymbol }} K</Kbd>
      </KbdGroup>
    </button>

    <CommandDialog v-model:open="open">
      <CommandInput placeholder="Search presets..." />
      <CommandList class="h-[50vh]! max-h-[50vh]">
        <CommandEmpty>No results found.</CommandEmpty>
        <CommandGroup heading="Presets">
          <CommandItem
            v-for="preset in index"
            :key="preset.id"
            :value="preset.title"
            class="tracking-tight"
            @select="go(preset.id)"
          >
            <Icon name="hugeicons:arrow-right-02" class="mr-2 size-4 shrink-0" />
            <span class="truncate">{{ preset.title }}</span>
            <span
              v-if="collectionLabel(preset)"
              class="text-muted-foreground ml-auto truncate pl-2 text-xs tracking-tight"
            >
              {{ collectionLabel(preset) }}
            </span>
          </CommandItem>
        </CommandGroup>
      </CommandList>
    </CommandDialog>
  </div>
</template>

<script setup>
import { useShaderPresets } from "@/components/shaders-docs/useShaderPresets";

const { index, collectionsById } = useShaderPresets();

const open = ref(false);
const router = useRouter();
const { metaSymbol } = useShortcuts();

async function go(id) {
  await router.push(`/shaders/${id}`);
  open.value = false;
}

// Hide the collection name when it just repeats the preset title (e.g. preset
// "Afternoon Sunlight" in collection "Afternoon Sunlight") - show it only when
// it adds information.
function collectionLabel(preset) {
  const name = collectionsById.get(preset.collectionId)?.name;
  if (!name || preset.title.toLowerCase().startsWith(name.toLowerCase())) return "";
  return name;
}

defineShortcuts({
  meta_k: {
    handler: () => {
      open.value = !open.value;
    },
  },
});
</script>
