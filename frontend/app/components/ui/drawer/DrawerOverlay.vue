<script lang="ts" setup>
import type { DrawerOverlayProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { DrawerOverlay } from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<DrawerOverlayProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <!--
    Classes copied verbatim from coss `apps/ui/registry/default/ui/drawer.tsx`
    (DrawerBackdrop). `--drawer-swipe-progress` is written by `DrawerContent`;
    reka registers it with `inherits: false` and only ever sets it once itself.
  -->
  <DrawerOverlay
    data-slot="drawer-backdrop"
    v-bind="delegatedProps"
    :class="
      cn(
        'fixed inset-0 z-50 bg-black/80 opacity-[calc(1-var(--drawer-swipe-progress))] transition-opacity duration-450 ease-[cubic-bezier(0.32,0.72,0,1)] data-ending-style:opacity-0 data-starting-style:opacity-0 data-ending-style:duration-[calc(var(--drawer-swipe-strength)*400ms)] data-swiping:duration-0 supports-[-webkit-touch-callout:none]:absolute',
        props.class
      )
    "
  />
</template>
