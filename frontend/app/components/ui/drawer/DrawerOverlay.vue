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
    Classes copied from coss `apps/ui/registry/default/ui/drawer.tsx`
    (DrawerBackdrop), with the two swipe variables renamed.

    coss reads `--drawer-swipe-progress` and `--drawer-swipe-strength` here, and
    in React that works because the backdrop's inline style object is a constant
    the renderer never re-applies. reka's port rebuilds that object on every
    render and lists it as a dynamic prop (`DrawerOverlayImpl.js:36-52`), so Vue
    writes `--drawer-swipe-progress: 0` back over ours on every re-render, and
    which value survives comes down to component id ordering in the scheduler.
    Reading variables only `DrawerContent` writes takes the race away entirely —
    reka is free to keep resetting its own copy, nothing here looks at it.
  -->
  <DrawerOverlay
    data-slot="drawer-backdrop"
    v-bind="delegatedProps"
    :class="
      cn(
        'fixed inset-0 z-50 bg-black/80 opacity-[calc(1-var(--drawer-backdrop-progress,0))] transition-opacity duration-450 ease-[cubic-bezier(0.32,0.72,0,1)] data-ending-style:opacity-0 data-starting-style:opacity-0 data-ending-style:duration-[calc(var(--drawer-backdrop-strength,1)*400ms)] data-swiping:duration-0 supports-[-webkit-touch-callout:none]:absolute',
        props.class
      )
    "
  />
</template>
