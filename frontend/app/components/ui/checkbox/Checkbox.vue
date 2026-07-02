<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import { Check, Minus } from "lucide-vue-next";
import type { CheckboxRootEmits, CheckboxRootProps } from "reka-ui";
import { CheckboxIndicator, CheckboxRoot, useForwardPropsEmits } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<CheckboxRootProps & { class?: HTMLAttributes["class"] }>();
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
        'cn-checkbox peer relative shrink-0 outline-none after:absolute after:-inset-x-3 after:-inset-y-2 disabled:cursor-not-allowed disabled:opacity-50',
        props.class
      )
    "
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="cn-checkbox-indicator grid place-content-center text-current transition-none"
    >
      <Minus v-if="isIndeterminate" class="size-3.5" />
      <Check v-else class="size-3.5" />
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
