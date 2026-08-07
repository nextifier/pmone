<script lang="ts" setup>
import type { DrawerTriggerProps } from "./core";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { DrawerTrigger } from "./core";
import { cn } from "@/lib/utils";

const props = defineProps<DrawerTriggerProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerMenuTrigger). -->
  <DrawerTrigger
    data-slot="drawer-menu-trigger"
    v-bind="delegatedProps"
    :class="
      cn(
        `flex min-h-9 w-full cursor-default select-none items-center gap-2 rounded-sm px-2 py-1 text-sm text-foreground outline-none hover:bg-accent hover:text-accent-foreground sm:min-h-8 [&_svg:not(:last-child)]:-mx-0.5 [&_svg:not([class*='size-'])]:size-4.5 sm:[&_svg:not([class*='size-'])]:size-4 [&_svg]:pointer-events-none [&_svg]:shrink-0`,
        props.class
      )
    "
  >
    <slot />
    <Icon name="lucide:chevron-right" class="ms-auto -me-0.5 opacity-80" />
  </DrawerTrigger>
</template>
