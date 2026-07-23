<script setup lang="ts">
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { reactiveOmit } from "@vueuse/core";
import { X } from "lucide-vue-next";
import type { TagsInputItemDeleteProps } from "reka-ui";
import { TagsInputItemDelete, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<TagsInputItemDeleteProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <!-- Same remove button as ComboboxChip: a ghost icon button whose data-slot collapses
       the chip's right padding. Sizing comes from the button, so the X needs no size. -->
  <Button variant="ghost" size="icon-xs" as-child>
    <TagsInputItemDelete
      v-bind="forwardedProps"
      data-slot="combobox-chip-remove"
      :class="cn('cn-combobox-chip-remove', props.class)"
    >
      <slot>
        <X class="pointer-events-none" />
      </slot>
    </TagsInputItemDelete>
  </Button>
</template>
