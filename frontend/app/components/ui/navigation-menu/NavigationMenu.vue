<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  NavigationMenuRoot,
  type NavigationMenuRootEmits,
  type NavigationMenuRootProps,
  useForwardPropsEmits,
} from "reka-ui";
import { cn } from "@/lib/utils";
import NavigationMenuViewport from "./NavigationMenuViewport.vue";

const props = withDefaults(
  defineProps<
    NavigationMenuRootProps & { class?: HTMLAttributes["class"]; viewport?: boolean }
  >(),
  { viewport: true },
);

const emits = defineEmits<NavigationMenuRootEmits>();

const delegatedProps = computed(() => {
  const { class: _, viewport: __, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <NavigationMenuRoot
    v-bind="forwarded"
    data-slot="navigation-menu"
    :data-viewport="viewport"
    :class="
      cn(
        'cn-navigation-menu group/navigation-menu relative flex max-w-max flex-1 items-center justify-center',
        props.class,
      )
    "
  >
    <slot />
    <NavigationMenuViewport v-if="viewport" />
  </NavigationMenuRoot>
</template>
