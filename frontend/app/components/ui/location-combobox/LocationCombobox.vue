<script setup lang="ts">
import {
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxInput,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { LucideCheck } from "lucide-vue-next";
import { ComboboxRoot, ComboboxVirtualizer, useFilter } from "reka-ui";
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
      <!-- The shared Combobox field, so the border, focus ring, invalid and disabled
           states are the same here as in every other combobox. -->
      <ComboboxInput
        v-model="searchTerm"
        :display-value="() => modelValue || ''"
        :placeholder="placeholder || 'Select'"
        :disabled="disabled"
        class="w-full"
      />
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
