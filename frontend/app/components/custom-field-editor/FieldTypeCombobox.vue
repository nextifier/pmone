<template>
  <Combobox
    :model-value="selectedOption"
    :disabled="disabled"
    ignore-filter
    auto-highlight
    @update:model-value="onSelect"
    @update:open="onOpenChange"
  >
    <ComboboxAnchor class="w-full">
      <ComboboxInput
        :id="id"
        v-model="query"
        :display-value="() => selectedOption?.label ?? ''"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-invalid="ariaInvalid"
        class="w-full"
      >
        <!--
          The chosen type's own glyph, inside the field. Every option in the
          list carries one, so without it the closed state is the only place in
          the flow where the type has no icon.
        -->
        <InputGroupAddon v-if="selectedOption" align="inline-start">
          <Icon :name="selectedOption.icon" class="text-muted-foreground size-4 shrink-0" />
        </InputGroupAddon>
      </ComboboxInput>
    </ComboboxAnchor>

    <ComboboxList class="w-(--reka-combobox-trigger-width)">
      <ComboboxViewport class="max-h-72 p-1">
        <ComboboxEmpty class="px-2 py-4 text-sm">No field type found.</ComboboxEmpty>

        <ComboboxGroup v-for="group in visibleGroups" :key="group.key" :heading="group.label">
          <ComboboxItem v-for="type in group.types" :key="type.value" :value="type">
            <span class="flex min-w-0 items-center gap-x-2">
              <Icon :name="type.icon" class="text-muted-foreground size-4 shrink-0" />
              <span class="truncate">{{ type.label }}</span>
            </span>
            <ComboboxItemIndicator />
          </ComboboxItem>
        </ComboboxGroup>
      </ComboboxViewport>
    </ComboboxList>
  </Combobox>
</template>

<script setup>
/**
 * The one field-type picker. Every surface that creates a custom field - the
 * form builder, brand fields, ticket contexts, ops documents - used to roll its
 * own `<Select>` with the same grouped markup copied four times, and the form
 * builder had a fourth variant: a grid of 34 illustrated cards.
 *
 * A combobox because the catalog is ~34 types across six groups: a select makes
 * you scroll it, this lets you type "rat" and land on Rating.
 */
import {
  Combobox,
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxInput,
  ComboboxItem,
  ComboboxItemIndicator,
  ComboboxList,
  ComboboxViewport,
} from "@/components/ui/combobox";
import { InputGroupAddon } from "@/components/ui/input-group";
import { FIELD_GROUPS, FIELD_TYPES } from "@/lib/formFieldTypes";

const props = defineProps({
  /** The selected type key. */
  modelValue: { type: String, default: null },
  /**
   * Type keys this surface does not offer. Ticket contexts drop `file` (no
   * upload pipeline) and `section`; brand fields also drop `rich_text`.
   */
  exclude: { type: Array, default: () => [] },
  /**
   * Surface-specific extras that are not in the shared catalog, e.g. brand
   * fields' "Year Select" alias. Same shape as a catalog entry.
   */
  extraTypes: { type: Array, default: () => [] },
  /**
   * Lands on the native input inside `ComboboxInput` (it spreads `$attrs`), so
   * the caller's `<FieldLabel :for>` actually points at something.
   */
  id: { type: String, default: undefined },
  disabled: { type: Boolean, default: false },
  ariaInvalid: { type: Boolean, default: false },
  placeholder: { type: String, default: "Select a field type" },
});

const emit = defineEmits(["update:modelValue"]);

const query = ref("");

const allTypes = computed(() => [
  ...Object.entries(FIELD_TYPES)
    .filter(([value]) => !props.exclude.includes(value))
    .map(([value, config]) => ({
      value,
      label: config.label,
      icon: config.icon,
      group: config.group,
    })),
  ...props.extraTypes,
]);

const selectedOption = computed(
  () => allTypes.value.find((type) => type.value === props.modelValue) ?? null
);

/**
 * Filtering is ours (`ignore-filter`) because reka matches on the item's text
 * content, and matching the type key too is what lets "textarea" find "Long
 * Text" and "checkbox" find "Checkboxes".
 */
const visibleGroups = computed(() => {
  const needle = query.value.trim().toLowerCase();
  const matches = needle
    ? allTypes.value.filter(
        (type) =>
          type.label.toLowerCase().includes(needle) || type.value.toLowerCase().includes(needle)
      )
    : allTypes.value;

  return FIELD_GROUPS.map((group) => ({
    ...group,
    types: matches.filter((type) => type.group === group.key),
  })).filter((group) => group.types.length);
});

const onSelect = (option) => {
  if (option?.value) emit("update:modelValue", option.value);
};

/** Reopening with the last search still in the box would hide most of the list. */
const onOpenChange = (open) => {
  if (open) query.value = "";
};
</script>
