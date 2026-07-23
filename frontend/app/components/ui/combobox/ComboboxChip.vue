<script setup lang="ts">
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { X } from "lucide-vue-next";
import type { AcceptableInputValue } from "reka-ui";
import { TagsInputItem, TagsInputItemDelete, TagsInputItemText } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { useSlots } from "vue";

const props = withDefaults(
  defineProps<{
    /** The entry in the ComboboxChips model this chip represents. */
    value: AcceptableInputValue;
    class?: HTMLAttributes["class"];
    /** Render the trailing remove button. */
    showRemove?: boolean;
    disabled?: boolean;
  }>(),
  { showRemove: true }
);

const slots = useSlots();
</script>

<template>
  <TagsInputItem
    :value="props.value"
    :disabled="props.disabled"
    data-slot="combobox-chip"
    :class="
      cn(
        'cn-combobox-chip has-disabled:pointer-events-none has-disabled:cursor-not-allowed has-disabled:opacity-50',
        props.class
      )
    "
  >
    <!-- Without a slot, TagsInputItemText renders the model entry (through the
         displayValue given to ComboboxChips). -->
    <slot v-if="slots.default" />
    <TagsInputItemText v-else />

    <!-- `cn-combobox-chip` keys its right padding off `has-data-[slot=combobox-chip-remove]`,
         so this data-slot must stay even if the button is restyled. TagsInputItemDelete
         removes the entry from the shared model, which is also the combobox value. -->
    <Button v-if="showRemove" variant="ghost" size="icon-xs" as-child>
      <TagsInputItemDelete data-slot="combobox-chip-remove" class="cn-combobox-chip-remove">
        <X class="pointer-events-none" />
      </TagsInputItemDelete>
    </Button>
  </TagsInputItem>
</template>
