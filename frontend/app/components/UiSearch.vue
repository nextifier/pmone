<template>
  <div>
    <button
      class="border-border bg-card hover:bg-muted text-muted-foreground flex h-8 w-full max-w-xs items-center gap-x-2 justify-self-end rounded-lg border px-2.5 text-sm tracking-tight transition"
      @click="open = true"
    >
      <Icon name="hugeicons:search-01" class="size-4 shrink-0" />
      <span class="truncate">Search components</span>
      <KbdGroup class="ml-auto hidden shrink-0 sm:inline-flex">
        <Kbd>{{ metaSymbol }} K</Kbd>
      </KbdGroup>
    </button>

    <CommandDialog v-model:open="open">
      <CommandInput placeholder="Search components..." />
      <CommandList class="h-[50vh]! max-h-[50vh]">
        <CommandEmpty>No results found.</CommandEmpty>
        <CommandGroup v-for="group in sidebarNav" :key="group.label" :heading="group.label">
          <CommandItem
            v-for="item in group.items"
            :key="item.name"
            :value="item.title"
            class="tracking-tight"
            @select="navigateToItem(item.name)"
          >
            <Icon name="hugeicons:arrow-right-02" class="mr-2 size-4" />
            <span>{{ item.title }}</span>
          </CommandItem>
        </CommandGroup>
      </CommandList>
    </CommandDialog>
  </div>
</template>

<script setup>
import { sidebarNav } from "@/components/ui-docs/sidebar-nav";

const open = ref(false);
const router = useRouter();
const { metaSymbol } = useShortcuts();

async function navigateToItem(name) {
  await router.push(`/ui/${name}`);
  open.value = false;
}

defineShortcuts({
  meta_k: {
    handler: () => {
      open.value = !open.value;
    },
  },
});
</script>
