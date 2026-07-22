<script setup lang="ts">
import {
  Combobox,
  ComboboxAnchor,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { LucideCheck, LucideX } from "lucide-vue-next";
import { useFilter } from "reka-ui";
import { computed, watch } from "vue";
import type { Option } from ".";

interface MultySelectProps {
  defaultOptions?: Option[];
  /** manually controlled options */
  options: Option[];
  placeholder?: string;
  /**
   * Clear-all button. Hidden by default: ui.shadcn.com's multi combobox has no
   * clear-all, chips are removed one by one. Pass false to bring it back.
   */
  hideClearAllButton?: boolean;
  openOnFocus?: boolean;
  openOnClick?: boolean;
  disabled?: boolean;
}

const query = defineModel<string>("query", {
  default: "",
});

const modelValue = defineModel<Option[]>("modelValue", {
  default: () => [],
});

const {
  defaultOptions,
  options,
  placeholder,
  hideClearAllButton = true,
  openOnFocus = true,
  openOnClick = true,
  disabled = false,
} = defineProps<MultySelectProps>();

const emits = defineEmits<{
  (e: "update:modelValue", payload: Option[]): void;
  (e: "update:query", payload: string): void;
}>();

const { contains } = useFilter({ sensitivity: "base" });

// Selected options stay in the list with a check, the way upstream does it, so the
// list never reflows under the cursor as you pick. Only the query filters.
const filteredOptions = computed(() =>
  options.filter(
    (option) => contains(option.value, query.value) || contains(option.label, query.value)
  )
);

watch(
  modelValue,
  () => {
    query.value = "";
  },
  { deep: true }
);

const removableCount = computed(() => modelValue.value.filter((item) => !item.fixed).length);

function clearAll() {
  modelValue.value = modelValue.value.filter((item) => item.fixed);
}
</script>

<template>
  <!-- Composes the shared Combobox chips parts, so the field, chip and remove button
       are the same markup (and the same cn-combobox-chip* rules) as the Combobox
       Multiple example. `fixed` options render without a remove button. -->
  <Combobox
    v-model="modelValue"
    multiple
    ignore-filter
    :disabled="disabled"
    :open-on-focus="openOnFocus"
    :open-on-click="openOnClick"
  >
    <ComboboxAnchor class="w-full">
      <ComboboxChips
        v-model="modelValue"
        :disabled="disabled"
        :display-value="(option) => (option as Option).label"
        class="w-full"
      >
        <ComboboxChip
          v-for="item in modelValue"
          :key="item.value"
          :value="item"
          :show-remove="!item.fixed"
        />
        <ComboboxChipsInput :placeholder="placeholder || 'Select'" />
        <button
          v-if="!hideClearAllButton && removableCount"
          type="button"
          class="text-muted-foreground/80 hover:text-foreground focus-visible:ring-ring/50 ml-auto flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-sm outline-none focus-visible:ring-2"
          aria-label="Clear all"
          @click="clearAll"
        >
          <LucideX class="size-3.5" aria-hidden="true" />
        </button>
      </ComboboxChips>
    </ComboboxAnchor>

    <ComboboxList class="w-(--reka-combobox-trigger-width)">
      <!-- The viewport carries `cn-combobox-list` (overflow-y-auto + max-height).
           Without it the panel keeps `cn-combobox-content`'s `overflow-hidden max-h-72`
           and a long option list is clipped with no way to scroll. -->
      <ComboboxViewport>
        <ComboboxEmpty>No results found.</ComboboxEmpty>

        <ComboboxGroup v-if="filteredOptions.length">
          <ComboboxItem
            v-for="option in filteredOptions"
            :key="option.value"
            :value="option"
            :disabled="option.disabled"
          >
            {{ option.label }}

            <ComboboxItemIndicator>
              <LucideCheck class="ml-auto size-4" />
            </ComboboxItemIndicator>
          </ComboboxItem>
        </ComboboxGroup>
      </ComboboxViewport>
    </ComboboxList>
  </Combobox>
</template>
