<script lang="ts" setup>
import type { DrawerSwipeAreaProps } from "./core";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
// Aliased: Nuxt auto-registers this file as a global `DrawerSwipeArea`, which
// would otherwise shadow the primitive and make the template render itself.
import { DrawerSwipeArea as SwipeAreaPrimitive } from "./core";
import { cn } from "@/lib/utils";

const props = defineProps<DrawerSwipeAreaProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <!-- Edge strip that opens the drawer on swipe, without a visible trigger. -->
  <SwipeAreaPrimitive
    data-slot="drawer-swipe-area"
    v-bind="delegatedProps"
    :class="cn(props.class)"
  >
    <slot />
  </SwipeAreaPrimitive>
</template>
