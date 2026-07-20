<script setup lang="ts">
import type { HTMLAttributes } from "vue";
import { computed } from "vue";
import type { ButtonVariants } from "../button";
import { Button } from "../button";
import { cn } from "@/lib/utils";
import {
  useMessageScroller,
  useMessageScrollerScrollable,
} from "./context";
import type { MessageScrollerButtonDirection } from "./engine";

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes["class"];
    direction?: MessageScrollerButtonDirection;
    behavior?: ScrollBehavior;
    variant?: ButtonVariants["variant"];
    size?: ButtonVariants["size"];
  }>(),
  {
    direction: "end",
    behavior: "smooth",
    variant: "secondary",
    size: "iconSm",
  }
);

const { scrollToEnd, scrollToStart } = useMessageScroller();
const scrollable = useMessageScrollerScrollable();
const active = computed(() =>
  props.direction === "start" ? scrollable.value.start : scrollable.value.end
);

function onClick(event: MouseEvent): void {
  if (!active.value) {
    return;
  }
  (event.currentTarget as HTMLElement).blur();
  if (props.direction === "start") {
    scrollToStart({ behavior: props.behavior });
  } else {
    scrollToEnd({ behavior: props.behavior });
  }
}
</script>

<template>
  <Button
    data-slot="message-scroller-button"
    :data-direction="direction"
    :data-active="active ? 'true' : 'false'"
    :data-variant="variant"
    :data-size="size"
    :variant="variant"
    :size="size"
    :tabindex="active ? undefined : -1"
    :inert="active ? undefined : true"
    :class="
      cn(
        'absolute left-1/2 z-10 -translate-x-1/2 rounded-full border bg-background shadow-sm transition-[translate,scale,opacity] duration-200 hover:bg-muted data-[active=false]:pointer-events-none data-[active=false]:scale-95 data-[active=false]:opacity-0 data-[active=true]:scale-100 data-[active=true]:opacity-100 data-[direction=end]:bottom-4 data-[direction=start]:top-4 data-[direction=start]:[&_svg]:rotate-180',
        props.class
      )
    "
    @click="onClick"
  >
    <slot>
      <Icon name="hugeicons:arrow-down-02" />
      <span class="sr-only">{{
        direction === "end" ? "Scroll to end" : "Scroll to start"
      }}</span>
    </slot>
  </Button>
</template>
