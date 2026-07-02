<script setup lang="ts">
import { cn } from "@/lib/utils";
import type { ComboboxGroupProps } from "reka-ui";
import { ComboboxGroup, ComboboxLabel } from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

const props = defineProps<
  ComboboxGroupProps & {
    class?: HTMLAttributes["class"];
    heading?: string;
  }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});
</script>

<template>
  <ComboboxGroup
    v-bind="delegatedProps"
    :class="
      cn(
        'cn-command-group',
        props.class
      )
    "
  >
    <ComboboxLabel
      v-if="heading"
      data-slot="command-group-heading"
      class="cn-command-group-heading"
    >
      {{ heading }}
    </ComboboxLabel>
    <slot />
  </ComboboxGroup>
</template>
