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
import { Flag } from "@/components/ui/flag";
import { InputGroupAddon } from "@/components/ui/input-group";
import { LucideCheck } from "@lucide/vue";
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
  /**
   * Show a country flag beside each option and beside the selected value.
   * Only turn this on for lists whose `option.value` is an ISO 3166-1 alpha-2
   * code. Province/city lists key off numeric codes; the `isIso2` guard below
   * makes a stray `show-flag` on those a no-op rather than a broken image.
   */
  showFlag?: boolean;
}

const modelValue = defineModel<string>("modelValue", { default: "" });

const {
  options,
  placeholder,
  disabled,
  pinned = [],
  showFlag = false,
} = defineProps<LocationComboboxProps>();

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

const isIso2 = (value: string) => /^[A-Za-z]{2}$/.test(value);

// Hidden while the user types, because `searchTerm` has replaced the display
// value by then and the flag would no longer describe what the field reads.
// Reka resets `searchTerm` to the selected label on close and this component
// clears it on open, so both of those states keep the flag and the field never
// jumps width on the most common interaction.
const showSelectedFlag = computed(
  () =>
    showFlag &&
    !isSearching.value &&
    !!selectedOption.value &&
    isIso2(selectedOption.value.value)
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
      >
        <!-- Rendered last but `order-first` on the inline-start addon puts it
             left of the input, and `cn-input-group` tightens the input's own
             left padding to match. -->
        <InputGroupAddon v-if="showSelectedFlag" align="inline-start">
          <Flag :country="selectedOption!.value" :country-name="selectedOption!.label" />
        </InputGroupAddon>
      </ComboboxInput>
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
            <!-- Fixed box the width of a Flag (24×16) so the flagless "None"
                 row still lines its label up with the country rows. An empty
                 <Flag> would not do: it always paints its placeholder tint. -->
            <span v-if="showFlag" class="flex h-4 w-6 shrink-0 items-center justify-center">
              <Flag
                v-if="isIso2(option.value)"
                :country="option.value"
                :country-name="option.label"
              />
            </span>
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
