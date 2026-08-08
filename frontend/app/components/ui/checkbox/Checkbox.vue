<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import { Minus } from "@lucide/vue";
import type { CheckboxRootEmits, CheckboxRootProps } from "reka-ui";
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<
  CheckboxRootProps & { class?: HTMLAttributes["class"] }
>();
const emits = defineEmits<CheckboxRootEmits>();

const delegatedProps = reactiveOmit(props, "class");

const forwarded = useForwardPropsEmits(delegatedProps, emits);

const isIndeterminate = computed(() => props.modelValue === "indeterminate");
</script>

<template>
  <CheckboxRoot
    data-slot="checkbox"
    v-bind="forwarded"
    :class="
      cn(
        'cn-checkbox peer relative shrink-0 cursor-pointer outline-none after:absolute after:-inset-x-3 after:-inset-y-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class,
      )
    "
  >
    <!-- Only the tick strokes itself on (transitions-dev 25). The indeterminate
         dash is a state you land in, not a completion, so it stays instant.

         Two things the draw depends on, both easy to break by editing the SVG:

         `t-check-draw` is what the keyframes hook onto - drop the class and the
         tick just appears with no animation at all.

         The path is lucide's check with its points reversed. Lucide ships
         `M20 6L9 17l-5-5`, starting at the long arm's tip, which makes a
         dashoffset draw run backwards: the stroke grows down the long arm first
         and the short arm lands last. Listing the same points in the other order
         draws identical geometry but starts at the short arm, so the tick writes
         itself the way a hand would. If this path changes, recompute --check-len
         in transitions.css - it must be the path length rounded up. -->
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="cn-checkbox-indicator grid place-content-center text-current transition-none"
    >
      <Minus v-if="isIndeterminate" class="size-3.5" />

      <svg
        v-else
        aria-hidden="true"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        class="t-check-draw size-3.5"
      >
        <path
          fill="none"
          stroke="currentColor"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="3"
          d="M4 12l5 5L20 6"
        />
      </svg>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
