<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { HoverCardContentProps } from "reka-ui";
import { HoverCardContent, HoverCardPortal, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<HoverCardContentProps & { class?: HTMLAttributes["class"] }>(),
  {
    sideOffset: 4,
  }
);

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <HoverCardPortal>
    <HoverCardContent
      data-slot="hover-card-content"
      v-bind="forwardedProps"
      :class="
        cn(
          'cn-hover-card-content cn-hover-card-content-logical z-50 origin-(--reka-hover-card-content-transform-origin) outline-hidden',
          props.class
        )
      "
    >
      <slot />
    </HoverCardContent>
  </HoverCardPortal>
</template>
