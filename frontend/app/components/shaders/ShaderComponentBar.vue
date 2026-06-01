<script setup>
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";

/**
 * Floating component palette anchored at the bottom-centre of the editor canvas
 * (mirrors the shaders.com design editor). One trigger per registry category;
 * each opens a searchable list of that category's components. Selecting one
 * emits `add` with the component name.
 */
const props = defineProps({
  // [{ category, components: [{ name, ... }] }] from useShaderRegistry().grouped
  groups: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["add"]);

const CATEGORY_ICONS = {
  Textures: "hugeicons:paint-board",
  Shapes: "hugeicons:shapes",
  "Shape Effects": "hugeicons:diamond-02",
  Blurs: "hugeicons:blur",
  Distortions: "hugeicons:flow-square",
  Stylize: "hugeicons:sparkles",
  Adjustments: "hugeicons:color-picker",
  Interactive: "hugeicons:cursor-magic-selection-03",
  Utilities: "hugeicons:folder-01",
};

const iconFor = (category) => CATEGORY_ICONS[category] ?? "hugeicons:cube";

const openCategory = ref(null);

function add(name) {
  emit("add", name);
  openCategory.value = null;
}
</script>

<template>
  <div
    class="bg-popover/80 ring-border flex items-center gap-x-1 rounded-2xl p-1.5 shadow-lg ring-1 backdrop-blur-xl"
  >
    <Popover
      v-for="group in groups"
      :key="group.category"
      :open="openCategory === group.category"
      @update:open="(o) => (openCategory = o ? group.category : null)"
    >
      <PopoverTrigger as-child>
        <Button
          v-tippy="group.category"
          variant="ghost"
          size="icon"
          class="text-muted-foreground hover:text-foreground size-11 rounded-xl"
          :class="openCategory === group.category && 'bg-muted text-foreground'"
        >
          <Icon :name="iconFor(group.category)" class="size-5.5" />
        </Button>
      </PopoverTrigger>
      <PopoverContent side="top" align="center" :side-offset="10" class="w-56 p-0">
        <Command>
          <CommandInput :placeholder="`Search ${group.category.toLowerCase()}…`" />
          <CommandList class="max-h-72">
            <CommandEmpty>No component.</CommandEmpty>
            <CommandGroup :heading="group.category">
              <CommandItem
                v-for="comp in group.components"
                :key="comp.name"
                :value="comp.name"
                class="tracking-tight"
                @select="add(comp.name)"
              >
                {{ comp.name }}
              </CommandItem>
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  </div>
</template>
