<script setup lang="ts">
import {
  ComboboxAnchor,
  ComboboxGroup,
  ComboboxItem,
  ComboboxList,
} from "@/components/ui/combobox";
import { cn } from "@/lib/utils";
import { LucideCheck, LucideChevronsUpDown, LucideSearch, LucideX } from "lucide-vue-next";
import { ComboboxRoot, ComboboxTrigger, useFilter } from "reka-ui";
import { computed, ref } from "vue";
import type { Option } from ".";

interface ComboboxMultiSelectProps {
  options: Option[];
  placeholder?: string;
}

const { options, placeholder } = defineProps<ComboboxMultiSelectProps>();

const modelValue = defineModel<Option[]>("modelValue", {
  default: () => [],
});

const query = ref("");

const { contains } = useFilter({ sensitivity: "base" });

const filteredOptions = computed(() =>
  options.filter(
    (option) =>
      contains(option.value, query.value) ||
      contains(option.label, query.value)
  )
);

const removeTag = (index: number) => {
  modelValue.value = modelValue.value.filter((_, i) => i !== index);
};

const searchInputRef = ref<HTMLInputElement>();

const handleOpenChange = (open: boolean) => {
  if (open) {
    nextTick(() => searchInputRef.value?.focus());
  } else {
    query.value = "";
  }
};
</script>

<template>
  <ComboboxRoot
    v-model="modelValue"
    multiple
    ignore-filter
    @update:open="handleOpenChange"
  >
    <ComboboxAnchor class="w-full">
      <ComboboxTrigger
        :class="
          cn(
            'border-input focus-visible:border-ring focus-visible:ring-ring has-aria-invalid:ring-destructive/20 dark:has-aria-invalid:ring-destructive/40 has-aria-invalid:border-destructive flex w-full min-h-[38px] items-center rounded-md border bg-transparent px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[1px] disabled:cursor-not-allowed disabled:opacity-50'
          )
        "
      >
        <div class="flex flex-1 flex-wrap gap-1">
          <span
            v-for="(item, index) in modelValue"
            :key="item.value"
            class="bg-background text-secondary-foreground inline-flex h-6 items-center gap-1 rounded-md border px-2 text-xs font-medium"
          >
            {{ item.label }}
            <span
              role="button"
              tabindex="-1"
              class="text-muted-foreground/80 hover:text-foreground -mr-1 cursor-pointer rounded-sm p-0.5"
              @click.stop.prevent="removeTag(index)"
            >
              <LucideX class="size-3" aria-hidden="true" />
            </span>
          </span>
          <span
            v-if="!modelValue.length"
            class="text-muted-foreground/70"
          >
            {{ placeholder || "Select" }}
          </span>
        </div>
        <LucideChevronsUpDown
          class="text-muted-foreground/80 size-4 shrink-0"
          aria-hidden="true"
        />
      </ComboboxTrigger>
    </ComboboxAnchor>

    <ComboboxList class="w-(--reka-combobox-trigger-width)">
      <div class="flex h-9 items-center gap-2 border-b px-3">
        <LucideSearch class="size-4 shrink-0 opacity-50" />
        <input
          ref="searchInputRef"
          v-model="query"
          class="placeholder:text-muted-foreground flex h-10 w-full bg-transparent py-3 text-sm outline-hidden"
          :placeholder="placeholder || 'Search...'"
          @keydown.stop
        />
      </div>

      <div v-if="!filteredOptions.length" class="px-2 py-4 text-center text-sm">
        No results found.
      </div>

      <ComboboxGroup class="max-h-60 overflow-y-auto p-1">
        <ComboboxItem
          v-for="option in filteredOptions"
          :key="option.value"
          :value="option"
          :disabled="option.disabled"
        >
          <span
            :class="
              cn(
                'mr-2 flex size-4 shrink-0 items-center justify-center',
                modelValue.some((v) => v.value === option.value)
                  ? 'text-foreground'
                  : 'opacity-0'
              )
            "
          >
            <LucideCheck class="size-4" />
          </span>
          {{ option.label }}
        </ComboboxItem>
      </ComboboxGroup>
    </ComboboxList>
  </ComboboxRoot>
</template>
