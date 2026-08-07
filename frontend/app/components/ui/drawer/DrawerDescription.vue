<script lang="ts" setup>
import type { DrawerDescriptionProps } from "./core";
import type { HTMLAttributes } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { DrawerDescription } from "./core";
import { cn } from "@/lib/utils";

const props = defineProps<DrawerDescriptionProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <!-- Classes copied verbatim from coss drawer.tsx (DrawerDescription). -->
  <DrawerDescription
    data-slot="drawer-description"
    v-bind="delegatedProps"
    :class="cn('text-muted-foreground text-sm', props.class)"
  >
    <slot />
  </DrawerDescription>
</template>
