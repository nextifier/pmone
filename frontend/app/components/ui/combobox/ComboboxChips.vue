<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { AcceptableInputValue } from "reka-ui";
import { TagsInputRoot } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  disabled?: boolean;
  /** Render an object value as chip text. Forwarded to TagsInputRoot. */
  displayValue?: (value: AcceptableInputValue) => string;
}>();

const model = defineModel<AcceptableInputValue[]>({ default: () => [] });

// Clicking the padding around the chips should focus the search field, the way a real
// input behaves. Ignore clicks that land on a chip's remove button or the input itself.
function focusInput(event: PointerEvent) {
  const target = event.target as HTMLElement | null;
  if (!target || target.closest("button") || target.tagName === "INPUT") return;
  event.preventDefault();
  (event.currentTarget as HTMLElement).querySelector("input")?.focus();
}
</script>

<template>
  <!-- Chips container for `multiple` comboboxes. Reka has no Chip primitives (those are
       Base UI), so this is TagsInput underneath — the same wiring MultiSelect uses —
       which brings the real keyboard semantics: Backspace removes the last chip and
       arrows move between chips. `cn-combobox-chips` keys its padding off
       `has-data-[slot=combobox-chip]`, so ComboboxChip must keep that data-slot.
       Bind the same model as the Combobox and place this inside ComboboxAnchor. -->
  <TagsInputRoot
    v-model="model"
    delimiter=""
    :disabled="props.disabled"
    :display-value="props.displayValue"
    data-slot="combobox-chips"
    :class="cn('cn-combobox-chips cursor-text', props.class)"
    @pointerdown="focusInput"
  >
    <slot />
  </TagsInputRoot>
</template>
