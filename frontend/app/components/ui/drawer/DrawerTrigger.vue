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
  <DrawerTrigger data-slot="drawer-trigger" v-bind="delegatedProps" :class="cn(props.class)">
    <slot />
  </DrawerTrigger>
</template>
