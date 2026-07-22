<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { ComboboxInputEmits, ComboboxInputProps } from "reka-ui";
import { ComboboxInput, TagsInputInput, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

defineOptions({
  inheritAttrs: false,
});

const props = defineProps<ComboboxInputProps & { class?: HTMLAttributes["class"] }>();

const emits = defineEmits<ComboboxInputEmits>();

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <!-- The search field inside ComboboxChips. It is the combobox input rendered AS the
       TagsInput input, so typing filters the list while Backspace on an empty field
       removes the last chip. Enter is swallowed so TagsInput never turns free text into
       a chip — only picking from the list adds one. -->
  <ComboboxInput v-bind="{ ...forwarded, ...$attrs }" as-child>
    <TagsInputInput
      data-slot="combobox-chip-input"
      :class="
        cn(
          'cn-combobox-chip-input min-w-16 flex-1 bg-transparent px-0 py-0 outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50',
          props.class
        )
      "
      @keydown.enter.prevent
    />
  </ComboboxInput>
</template>
