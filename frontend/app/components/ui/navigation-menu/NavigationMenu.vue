<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  NavigationMenuRoot,
  type NavigationMenuRootEmits,
  type NavigationMenuRootProps,
  useForwardPropsEmits,
} from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<
  NavigationMenuRootProps & { class?: HTMLAttributes["class"] }
>();

const emits = defineEmits<NavigationMenuRootEmits>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <NavigationMenuRoot
    v-bind="forwarded"
    data-slot="navigation-menu"
    data-viewport="false"
    :class="
      cn(
        'cn-navigation-menu group/navigation-menu relative z-10 flex max-w-max flex-1 items-center justify-center',
        props.class,
      )
    "
  >
    <slot />
  </NavigationMenuRoot>
</template>
