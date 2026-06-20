<script setup lang="ts">
import { toggleVariants } from "@/components/ui/toggle";
import { cn } from "@/lib/utils";
import { reactiveOmit } from "@vueuse/core";
import type { VariantProps } from "class-variance-authority";
import type { ToggleGroupItemProps } from "reka-ui";
import { ToggleGroupItem, useForwardProps } from "reka-ui";
import type { HTMLAttributes } from "vue";
import { computed, inject } from "vue";

type ToggleGroupVariants = VariantProps<typeof toggleVariants>;
type GroupVariant = NonNullable<ToggleGroupVariants["variant"]> | "pill";

const props = defineProps<
  ToggleGroupItemProps & {
    class?: HTMLAttributes["class"];
    variant?: GroupVariant;
    size?: ToggleGroupVariants["size"];
  }
>();

const context = inject<{ variant?: GroupVariant; size?: ToggleGroupVariants["size"] }>(
  "toggleGroup"
);

const resolvedVariant = computed<GroupVariant>(
  () => context?.variant || props.variant || "default"
);

// CVA only knows the segmented variants; "pill" is rendered via pillClass, so
// fall back to "default" here (kept out of the template to avoid a TS cast).
const cvaVariant = computed<ToggleGroupVariants["variant"]>(() =>
  resolvedVariant.value === "pill" ? "default" : resolvedVariant.value
);

const delegatedProps = reactiveOmit(props, "class", "size", "variant");
const forwardedProps = useForwardProps(delegatedProps);

// Separated "pill" chip that matches the day picker on the event websites,
// instead of the default connected segmented look.
const pillClass =
  "inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-border bg-background px-3 py-2 text-sm font-medium tracking-tight outline-none transition-[color,box-shadow] hover:bg-muted disabled:pointer-events-none disabled:opacity-50 focus-visible:border-ring focus-visible:ring-ring focus-visible:ring-[1px] data-[state=on]:border-primary data-[state=on]:bg-primary/5 data-[state=on]:text-foreground data-[state=on]:ring-1 data-[state=on]:ring-primary";
</script>

<template>
  <ToggleGroupItem
    v-slot="slotProps"
    data-slot="toggle-group-item"
    :data-variant="resolvedVariant"
    :data-size="context?.size || size"
    v-bind="forwardedProps"
    :class="
      resolvedVariant === 'pill'
        ? cn(pillClass, props.class)
        : cn(
            toggleVariants({
              variant: cvaVariant,
              size: context?.size || size,
            }),
            'min-w-0 flex-1 shrink-0 rounded-none shadow-none first:rounded-l-md last:rounded-r-md focus:z-10 focus-visible:z-10 data-[variant=outline]:border-l-0 data-[variant=outline]:first:border-l',
            props.class
          )
    "
  >
    <slot v-bind="slotProps" />
  </ToggleGroupItem>
</template>
