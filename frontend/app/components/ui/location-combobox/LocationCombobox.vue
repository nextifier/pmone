<script setup lang="ts">
import {
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxSeparator,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { LucideCheck, LucideChevronsUpDown } from "lucide-vue-next";
import { ComboboxInput, ComboboxRoot, useFilter } from "reka-ui";
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
  >
    <ComboboxAnchor class="w-full">
      <div
        class="border-input focus-within:border-ring focus-within:ring-ring has-aria-invalid:ring-destructive/20 dark:has-aria-invalid:ring-destructive/40 has-aria-invalid:border-destructive relative flex h-9 w-full items-center rounded-md border text-sm tracking-tight transition-[color,box-shadow] outline-none focus-within:ring-[1px] has-disabled:pointer-events-none has-disabled:cursor-not-allowed has-disabled:opacity-50"
      >
        <ComboboxInput
          v-model="searchTerm"
          :display-value="() => modelValue || ''"
          :placeholder="placeholder || 'Select...'"
          class="placeholder:text-muted-foreground/70 h-full w-full flex-1 bg-transparent px-3 py-1 text-sm outline-hidden"
        />
        <LucideChevronsUpDown class="text-muted-foreground mr-2 size-4 shrink-0" />
      </div>
    </ComboboxAnchor>

    <ComboboxList class="w-(--reka-combobox-trigger-width)">
      <ComboboxViewport>
        <ComboboxEmpty class="px-2 py-4 text-sm">No results found.</ComboboxEmpty>

        <ComboboxGroup>
          <ComboboxItem
            v-if="!isSearching"
            :value="noneOption"
            class="text-muted-foreground italic"
          >
            None
          </ComboboxItem>

          <template v-if="pinnedOptions.length">
            <ComboboxItem v-for="option in pinnedOptions" :key="option.value" :value="option">
              {{ option.label }}
              <ComboboxItemIndicator>
                <LucideCheck class="ml-auto size-4" />
              </ComboboxItemIndicator>
            </ComboboxItem>
            <ComboboxSeparator v-if="remainingOptions.length" />
          </template>

          <ComboboxItem v-for="option in remainingOptions" :key="option.value" :value="option">
            {{ option.label }}
            <ComboboxItemIndicator>
              <LucideCheck class="ml-auto size-4" />
            </ComboboxItemIndicator>
          </ComboboxItem>
        </ComboboxGroup>
      </ComboboxViewport>
    </ComboboxList>
  </ComboboxRoot>
</template>
