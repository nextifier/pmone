<script lang="ts" setup>
import type { DrawerCloseProps } from "./core";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { DrawerClose } from "./core";
import { cn } from "@/lib/utils";

const props = defineProps<DrawerCloseProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <DrawerClose data-slot="drawer-close" v-bind="delegatedProps" :class="cn(props.class)">
    <slot />
  </DrawerClose>
</template>
