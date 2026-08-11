<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { LabelProps } from "reka-ui";
import { Label } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<
  LabelProps & {
    class?: HTMLAttributes["class"];
    /**
     * Renders the required marker inside the label's own inline flow, so a label
     * that wraps keeps the `*` glued to its last word. It replaces the manual
     * `<span class="text-destructive-foreground">*</span>` call sites used to write, which
     * was a sibling flex item: the flex row parked it at the far end, vertically
     * centred against two lines of text.
     *
     * The slot is only wrapped when this is set, so labels that sit an icon
     * beside their text keep their separate flex children and `.cn-label`'s gap.
     * The wrapper needs no `min-w-0` - a flex item's automatic minimum size is
     * already min-content, which is exactly the "wrap between words, never
     * overflow" behaviour this wants.
     */
    required?: boolean;
  }
>();

const delegatedProps = reactiveOmit(props, "class", "required");
</script>

<template>
  <Label
    data-slot="label"
    v-bind="delegatedProps"
    :class="
      cn(
        'cn-label flex items-center select-none group-data-[disabled=true]:pointer-events-none peer-disabled:cursor-not-allowed',
        props.class
      )
    "
  >
    <slot v-if="!required" />

    <span v-else data-slot="label-text">
      <slot />
      <!-- An asterisk's ink sits high in the em box, so left alone it reads as a
           superscript rather than as part of the label. 0.15em drops its centre
           onto the cap-height centre - the line the eye actually reads as the
           middle of a word that starts with a capital. (x-height centre would be
           0.25em, which overshoots and looks like it fell off the baseline.)
           `relative` + `top` rather than a transform: it needs no inline-block,
           so the line box keeps its height. -->
      <span
        data-slot="label-required"
        class="text-destructive-foreground relative top-[0.15em] ms-1"
        aria-hidden="true"
        >*</span
      >
      <span class="sr-only"> (required)</span>
    </span>
  </Label>
</template>
