<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  NavigationMenuTrigger,
  type NavigationMenuTriggerProps,
  useForwardProps,
} from "reka-ui";
import { ChevronDown } from "lucide-vue-next";
import { navigationMenuTriggerStyle } from ".";
import { cn } from "@/lib/utils";

const props = defineProps<
  NavigationMenuTriggerProps & { class?: HTMLAttributes["class"] }
>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <NavigationMenuTrigger
    v-bind="forwardedProps"
    data-slot="navigation-menu-trigger"
    :class="cn(navigationMenuTriggerStyle(), 'group', props.class)"
  >
    <slot />
    <ChevronDown
      class="cn-navigation-menu-trigger-icon"
      aria-hidden="true"
    />
  </NavigationMenuTrigger>
</template>
