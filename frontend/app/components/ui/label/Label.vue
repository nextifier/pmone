<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { LabelProps } from "reka-ui";
import { Label } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<LabelProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");
</script>

<template>
  <Label
    data-slot="label"
    v-bind="delegatedProps"
    :class="
      cn(
        'cn-label flex items-center select-none group-data-[disabled=true]:pointer-events-none peer-disabled:cursor-not-allowed',
        props.class
      )
    "
  >
    <slot />
  </Label>
</template>
