<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { TagsInputRootEmits, TagsInputRootProps } from "reka-ui";
import { TagsInputRoot, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<TagsInputRootProps & { class?: HTMLAttributes["class"] }>();
const emits = defineEmits<TagsInputRootEmits>();

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);

// Clicking the padding around the tags should focus the entry field, the way a real
// input behaves. Ignore clicks that land on a tag's remove button or the input itself.
function focusInput(event: PointerEvent) {
  const target = event.target as HTMLElement | null;
  if (!target || target.closest("button") || target.tagName === "INPUT") return;
  event.preventDefault();
  (event.currentTarget as HTMLElement).querySelector("input")?.focus();
}
</script>

<template>
  <!-- Tags and combobox chips are the same thing on screen (and the same reka primitive
       underneath), so this field shares the `cn-combobox-chip*` rules and their
       data-slots rather than keeping a second set that drifts. `cn-combobox-chips`
       keys its padding off `has-data-[slot=combobox-chip]`, which TagsInputItem sets. -->
  <TagsInputRoot
    v-bind="forwarded"
    data-slot="combobox-chips"
    :class="
      cn(
        'cn-combobox-chips cursor-text',
        // The chips container reacts to an invalid descendant; TagsInput can also be
        // marked invalid on the root itself.
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:ring-3',
        'data-disabled:pointer-events-none data-disabled:opacity-50',
        props.class
      )
    "
    @pointerdown="focusInput"
  >
    <slot />
  </TagsInputRoot>
</template>
