<script setup lang="ts">
import { cn } from "@/lib/utils";
import {
  PopoverContent,
  type PopoverContentEmits,
  type PopoverContentProps,
  PopoverPortal,
  useForwardPropsEmits,
} from "reka-ui";
import { computed, type HTMLAttributes } from "vue";

defineOptions({
  inheritAttrs: false,
});

const props = withDefaults(
  defineProps<PopoverContentProps & { class?: HTMLAttributes["class"] }>(),
  {
    align: "center",
    sideOffset: 4,
  },
);
const emits = defineEmits<PopoverContentEmits>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <PopoverPortal>
    <PopoverContent
      v-bind="{ ...forwarded, ...$attrs }"
      data-slot="popover-content"
      :class="
        cn(
          'cn-popover-content cn-popover-content-logical z-50 w-72 origin-(--reka-popover-content-transform-origin) outline-hidden',
          'ease-(--dropdown-ease) data-open:duration-(--dropdown-open-dur) data-open:zoom-in-97 data-closed:duration-(--dropdown-close-dur) data-closed:zoom-out-99 motion-reduce:animate-none!',
          props.class,
        )
      "
    >
      <slot />
    </PopoverContent>
  </PopoverPortal>
</template>
