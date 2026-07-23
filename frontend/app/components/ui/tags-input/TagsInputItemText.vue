<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { TagsInputItemTextProps } from "reka-ui";
import { TagsInputItemText, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<TagsInputItemTextProps & { class?: HTMLAttributes["class"] }>();

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <!-- Size and padding come from the chip, as they do for combobox chips. -->
  <TagsInputItemText v-bind="forwardedProps" :class="cn(props.class)" />
</template>
