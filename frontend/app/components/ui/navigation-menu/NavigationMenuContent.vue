<script setup lang="ts">
import { type HTMLAttributes, computed } from "vue";
import {
  NavigationMenuContent,
  type NavigationMenuContentEmits,
  type NavigationMenuContentProps,
  useForwardPropsEmits,
} from "reka-ui";
import { cn } from "@/lib/utils";

const props = defineProps<
  NavigationMenuContentProps & { class?: HTMLAttributes["class"] }
>();

const emits = defineEmits<NavigationMenuContentEmits>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <NavigationMenuContent
    v-bind="forwarded"
    :class="
      cn(
        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-50 border-border bg-popover absolute top-full right-0 mt-3 flex w-56 origin-top-right flex-col gap-y-1 rounded-xl border px-2 py-4',
        props.class,
      )
    "
  >
    <slot />
  </NavigationMenuContent>
</template>
