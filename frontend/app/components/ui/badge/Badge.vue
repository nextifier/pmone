<script setup lang="ts">
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { PrimitiveProps } from "reka-ui";
import { Primitive } from "reka-ui";
import { computed } from "vue";
import type { HTMLAttributes } from "vue";
import type { BadgeVariants } from ".";
import { badgeDefaultIcons, badgeDotVariants, badgeIconVariants, badgeVariants } from ".";

const props = defineProps<
  PrimitiveProps & {
    variant?: BadgeVariants["variant"];
    icon?: string;
    withIcon?: boolean;
    plain?: boolean;
    class?: HTMLAttributes["class"];
  }
>();

const delegatedProps = reactiveOmit(props, "class", "variant", "icon", "withIcon", "plain");

const resolvedIcon = computed(
  () => props.icon ?? (props.withIcon ? badgeDefaultIcons[props.variant ?? "default"] : undefined)
);
</script>

<template>
  <Primitive
    data-slot="badge"
    :class="cn(badgeVariants({ plain }), !plain && resolvedIcon && 'pr-2.5', props.class)"
    v-bind="delegatedProps"
  >
    <Icon
      v-if="resolvedIcon"
      :name="resolvedIcon"
      :class="badgeIconVariants({ variant })"
    />
    <span
      v-else-if="variant !== 'outline'"
      :class="badgeDotVariants({ variant })"
      aria-hidden="true"
    />
    <span>
      <slot />
    </span>
  </Primitive>
</template>
