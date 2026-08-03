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
  <HoverCardPortal data-slot="hover-card-portal">
    <!-- Dropdown tokens, not tooltip: a hover card is an anchored content surface that
         grows from its trigger (and can be hovered into), so the tooltip's 50ms exit
         would read as a snap. Same shape-based tie-breaker the skill uses. -->
    <HoverCardContent
      data-slot="hover-card-content"
      v-bind="forwardedProps"
      :class="
        cn(
          'cn-hover-card-content cn-hover-card-content-logical z-50 origin-(--reka-hover-card-content-transform-origin) outline-hidden',
          'ease-(--dropdown-ease) data-open:duration-(--dropdown-open-dur) data-open:zoom-in-97 data-closed:duration-(--dropdown-close-dur) data-closed:zoom-out-99 motion-reduce:animate-none!',
          props.class
        )
      "
    >
      <slot />
    </HoverCardContent>
  </HoverCardPortal>
</template>
