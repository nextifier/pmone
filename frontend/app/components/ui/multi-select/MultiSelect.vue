<script setup lang="ts">
import {
  ComboboxAnchor,
  ComboboxGroup,
  ComboboxItem,
  ComboboxItemIndicator,
} from "@/components/ui/combobox";
import { LucideCheck, LucideChevronsUpDown, LucideX } from "lucide-vue-next";
import {
  ComboboxInput,
  ComboboxRoot,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
  TagsInputRoot,
  useFilter,
} from "reka-ui";
import { computed } from "vue";
import type { Option } from ".";

interface MultySelectProps {
  defaultOptions?: Option[];
  /** manually controlled options */
  options: Option[];
  placeholder?: string;
  hideClearAllButton?: boolean;
  openOnFocus?: boolean;
  openOnClick?: boolean;
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
  openOnFocus = true,
  openOnClick = true,
} = defineProps<MultySelectProps>();

const emits = defineEmits<{
  (e: "update:modelValue", payload: Option[]): void;
  (e: "update:query", payload: string): void;
}>();

const { contains } = useFilter({ sensitivity: "base" });

const filteredOptions = computed(() =>
  options.filter(
    (option) =>
      (contains(option.value, query.value) || contains(option.label, query.value)) &&
      !modelValue.value.some((item) => item.value === option.value)
  )
);

watch(
  modelValue,
  () => {
    query.value = "";
  },
  { deep: true }
);

const removeTag = (index: number) => {
  modelValue.value = modelValue.value.filter((item, i) => i !== index);
};

const focusInnerInput = (event: PointerEvent) => {
  const target = event.target as HTMLElement;
  if (target.closest("button") || target.tagName === "INPUT") return;
  event.preventDefault();
  (event.currentTarget as HTMLElement).querySelector("input")?.focus();
};
</script>

<template>
  <ComboboxRoot
    v-model="modelValue"
    multiple
    ignore-filter
    :open-on-focus="openOnFocus"
    :open-on-click="openOnClick"
  >
    <ComboboxAnchor class="w-full">
      <TagsInputRoot
        v-model="modelValue"
        delimiter=""
        class="cn-select-trigger focus-within:border-ring focus-within:ring-ring has-aria-invalid:ring-destructive/20 dark:has-aria-invalid:ring-destructive/40 has-aria-invalid:border-destructive relative flex h-auto min-h-(--cn-input-h,2.25rem) w-full min-w-0 cursor-text items-center gap-1 py-0 transition-[color,box-shadow] outline-none focus-within:ring-[1px] has-disabled:pointer-events-none has-disabled:cursor-not-allowed has-disabled:opacity-50"
        @pointerdown="focusInnerInput"
      >
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1">
          <TagsInputItem
            v-for="(item, index) in modelValue"
            :key="item.value"
            :value="item.label"
            class="animate-fadeIn bg-background text-secondary-foreground hover:bg-background relative inline-flex h-7 cursor-default items-center rounded-md border ps-2 pe-7 pl-2 text-sm font-medium transition-[color,opacity] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 data-fixed:pe-2"
          >
            <TagsInputItemText />
            <TagsInputItemDelete
              @click="() => removeTag(index)"
              class="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring absolute -inset-y-px -end-px flex size-7 items-center justify-center rounded-e-md border border-transparent p-0 outline-hidden transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
            >
              <LucideX class="size-4" aria-hidden="true" />
            </TagsInputItemDelete>
          </TagsInputItem>

          <ComboboxInput v-model="query" as-child>
            <TagsInputInput
              :placeholder="placeholder || 'Select'"
              class="placeholder:text-muted-foreground min-w-16 flex-1 bg-transparent px-0 py-0 outline-hidden disabled:cursor-not-allowed"
              @keydown.enter.prevent
            />
          </ComboboxInput>
        </div>
        <div class="pointer-events-none flex shrink-0 items-center gap-0.5">
          <button
            v-if="!hideClearAllButton && modelValue.length"
            type="button"
            class="text-muted-foreground/80 hover:text-foreground focus-visible:border-ring focus-visible:ring-ring pointer-events-auto flex size-6 items-center justify-center rounded-md border border-transparent transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
            aria-label="Clear all"
            @click="() => (modelValue = [])"
          >
            <LucideX class="size-4" aria-hidden="true" />
          </button>
          <LucideChevronsUpDown class="cn-select-trigger-icon shrink-0" aria-hidden="true" />
        </div>
      </TagsInputRoot>
    </ComboboxAnchor>

    <ComboboxList class="w-(--reka-combobox-trigger-width)">
      <ComboboxViewport>
        <ComboboxEmpty class="px-2 py-4">No results found.</ComboboxEmpty>

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
  </ComboboxRoot>
</template>
