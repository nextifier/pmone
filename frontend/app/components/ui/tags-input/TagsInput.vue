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
</script>

<template>
  <TagsInputRoot
    v-bind="forwarded"
    :class="
      cn(
        'cn-input flex h-auto min-h-(--cn-input-h,2.25rem) w-full min-w-0 flex-wrap items-center gap-2 px-2 py-0.5 text-sm transition-[color,box-shadow] outline-none',
        'focus-within:border-ring focus-within:ring-ring focus-within:ring-[1px]',
        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
        props.class
      )
    "
  >
    <slot />
  </TagsInputRoot>
</template>
