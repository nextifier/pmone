<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { TagsInputItemProps } from "reka-ui";
import { TagsInputItem, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<TagsInputItemProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <!-- Same chip as ComboboxChip. `cn-combobox-chip` keys its right padding off
       `has-data-[slot=combobox-chip-remove]`, which TagsInputItemDelete sets. -->
  <TagsInputItem
    v-bind="forwardedProps"
    data-slot="combobox-chip"
    :class="
      cn(
        'cn-combobox-chip has-disabled:pointer-events-none has-disabled:cursor-not-allowed has-disabled:opacity-50',
        // Arrow keys move focus between tags; the active one needs to show it.
        'data-[state=active]:ring-ring/50 ring-offset-background data-[state=active]:ring-2',
        props.class
      )
    "
  >
    <slot />
  </TagsInputItem>
</template>
