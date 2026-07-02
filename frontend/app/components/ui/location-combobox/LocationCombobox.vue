<script setup lang="ts">
import {
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { LucideCheck, LucideChevronsUpDown } from "lucide-vue-next";
import { ComboboxInput, ComboboxRoot, ComboboxVirtualizer, useFilter } from "reka-ui";
import { computed, ref, watch } from "vue";

interface Option {
  value: string;
  label: string;
  [key: string]: string | boolean | undefined;
}

interface LocationComboboxProps {
  options: Option[];
  placeholder?: string;
  disabled?: boolean;
  pinned?: string[];
}

const modelValue = defineModel<string>("modelValue", { default: "" });

const { options, placeholder, disabled, pinned = [] } = defineProps<LocationComboboxProps>();

const searchTerm = ref("");
const { contains } = useFilter({ sensitivity: "base" });

const selectedOption = computed(
  () => options.find((opt) => opt.label === modelValue.value) ?? null
);

const filteredOptions = computed(() =>
  options.filter(
    (option) => contains(option.label, searchTerm.value) || contains(option.value, searchTerm.value)
  )
);

const pinnedSet = computed(() => new Set(pinned));

const pinnedOptions = computed(() =>
  filteredOptions.value.filter((opt) => pinnedSet.value.has(opt.label))
);

const remainingOptions = computed(() =>
  filteredOptions.value.filter((opt) => !pinnedSet.value.has(opt.label))
);

const noneOption: Option = { value: "__none__", label: "None" };

const isSearching = computed(
  () => searchTerm.value !== "" && searchTerm.value !== modelValue.value
);

const flatOptions = computed<Option[]>(() => [
  ...(!isSearching.value ? [noneOption] : []),
  ...pinnedOptions.value,
  ...remainingOptions.value,
]);

const lastPinnedValue = computed(() => pinnedOptions.value.at(-1)?.value);

function handleSelect(option: Option) {
  if (option.value === "__none__") {
    modelValue.value = "";
  } else {
    modelValue.value = option.label;
  }
  searchTerm.value = "";
}

// Keep searchTerm in sync - when modelValue changes externally, clear search
watch(modelValue, () => {
  searchTerm.value = "";
});
</script>

<template>
  <ComboboxRoot
    :model-value="selectedOption"
    :ignore-filter="true"
    :open-on-focus="true"
    :disabled="disabled"
    @update:model-value="
      (val: any) => {
        if (val) handleSelect(val);
      }
    "
    @update:open="
      (open: boolean) => {
        if (open) searchTerm = '';
      }
    "
  >
    <ComboboxAnchor class="w-full">
      <div
        class="cn-select-trigger focus-within:border-ring focus-within:ring-ring has-aria-invalid:ring-destructive/20 dark:has-aria-invalid:ring-destructive/40 has-aria-invalid:border-destructive relative flex h-auto min-h-(--cn-input-h,2.25rem) w-full min-w-0 cursor-text items-center py-0 tracking-tight transition-[color,box-shadow] outline-none focus-within:ring-[1px] has-disabled:pointer-events-none has-disabled:cursor-not-allowed has-disabled:opacity-50"
        @pointerdown="
          (e: PointerEvent) => {
            if ((e.target as HTMLElement).tagName === 'INPUT') return;
            e.preventDefault();
            (e.currentTarget as HTMLElement).querySelector('input')?.focus();
          }
        "
      >
        <ComboboxInput
          v-model="searchTerm"
          :display-value="() => modelValue || ''"
          :placeholder="placeholder || 'Select...'"
          class="placeholder:text-muted-foreground h-full w-full flex-1 bg-transparent px-0 py-0 text-sm outline-hidden"
        />
        <LucideChevronsUpDown class="cn-select-trigger-icon shrink-0" />
      </div>
    </ComboboxAnchor>

    <ComboboxList class="w-(--reka-combobox-trigger-width)">
      <ComboboxViewport class="p-1">
        <ComboboxEmpty class="px-2 py-4 text-sm">No results found.</ComboboxEmpty>

        <ComboboxVirtualizer
          v-slot="{ option }"
          :options="flatOptions"
          :estimate-size="32"
          :text-content="(opt: Option) => opt.label"
        >
          <ComboboxItem
            :value="option"
            class="h-8 w-full"
            :class="[
              option.value === '__none__' && 'text-muted-foreground italic',
              option.value === lastPinnedValue &&
                remainingOptions.length > 0 &&
                'border-border rounded-b-none border-b',
            ]"
          >
            <span class="truncate">{{ option.label }}</span>
            <ComboboxItemIndicator>
              <LucideCheck class="ml-auto size-4" />
            </ComboboxItemIndicator>
          </ComboboxItem>
        </ComboboxVirtualizer>
      </ComboboxViewport>
    </ComboboxList>
  </ComboboxRoot>
</template>
