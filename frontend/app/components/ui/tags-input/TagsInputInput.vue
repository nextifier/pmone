<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { TagsInputInputProps } from "reka-ui";
import { TagsInputInput, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<TagsInputInputProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <!-- Same field as ComboboxChipsInput, minus the Enter guard: here Enter is what turns
       typed text into a tag. -->
  <TagsInputInput
    v-bind="forwardedProps"
    enterkeyhint="done"
    data-slot="combobox-chip-input"
    :class="
      cn(
        'cn-combobox-chip-input placeholder:text-muted-foreground min-w-16 flex-1 bg-transparent px-0 py-0 outline-none disabled:cursor-not-allowed disabled:opacity-50',
        props.class
      )
    "
  />
</template>
