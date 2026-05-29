<script setup lang="ts">
import type { TabsRootEmits, TabsRootProps } from "reka-ui";
import { TabsRoot, useForwardPropsEmits } from "reka-ui";
import { useSwipe } from "@vueuse/core";
import { computed, onMounted, provide, ref } from "vue";
import {
  TABS_CONTEXT,
  TABS_DEFAULTS,
  type TabsSize,
  type TabsVariant,
} from "./context";

const props = withDefaults(
  defineProps<
    TabsRootProps & {
      variant?: TabsVariant;
      size?: TabsSize;
      swipe?: boolean;
      swipeable?: boolean;
      swipeExclude?: string[];
    }
  >(),
  {
    variant: TABS_DEFAULTS.variant,
    size: TABS_DEFAULTS.size,
    swipe: false,
    swipeable: false,
    swipeExclude: () => [
      "[aria-roledescription='carousel']",
      ".pswp",
      "[role='tablist']",
      "[data-slot='table-container']",
    ],
  },
);

const emits = defineEmits<TabsRootEmits>();

const forwarded = useForwardPropsEmits(
  computed(() => {
    const {
      variant: _v,
      size: _s,
      swipe: _sw,
      swipeable: _swa,
      swipeExclude: _se,
      ...rest
    } = props;
    return rest as TabsRootProps;
  }),
  emits,
);

provide(TABS_CONTEXT, {
  variant: computed(() => props.variant),
  size: computed(() => props.size),
  swipeable: computed(() => props.swipeable),
});

const tabsRootInstance = ref<InstanceType<typeof TabsRoot> | null>(null);
const swipeContainerRef = ref<HTMLElement | null>(null);
const isSwipeExcluded = ref(false);

onMounted(() => {
  const inst = tabsRootInstance.value as unknown as { $el?: HTMLElement } | null;
  swipeContainerRef.value = inst?.$el ?? null;
});

useSwipe(swipeContainerRef, {
  passive: true,
  onSwipeStart(e) {
    if (!props.swipe) return;
    isSwipeExcluded.value = false;
    const target = e.target as Element | null;
    if (!target) return;

    // Nested Tabs: kalau closest tabs-root bukan root kita, berarti gesture
    // berasal dari child Tabs — biar child yang handle, parent skip.
    const closestTabs = target.closest("[data-slot='tabs']");
    if (closestTabs && closestTabs !== swipeContainerRef.value) {
      isSwipeExcluded.value = true;
      return;
    }

    if (props.swipeExclude.some((sel) => target.closest(sel))) {
      isSwipeExcluded.value = true;
    }
  },
  onSwipeEnd(_, direction) {
    if (!props.swipe) return;
    if (isSwipeExcluded.value) {
      isSwipeExcluded.value = false;
      return;
    }
    if (direction !== "left" && direction !== "right") return;

    const root = swipeContainerRef.value;
    if (!root) return;

    // Filter ke trigger yang langsung milik root ini (bukan dari nested Tabs).
    const triggers = Array.from(
      root.querySelectorAll<HTMLElement>('[role="tab"]:not([disabled])'),
    ).filter((t) => t.closest("[data-slot='tabs']") === root);
    if (triggers.length === 0) return;

    const activeIdx = triggers.findIndex((t) => t.dataset.state === "active");
    if (activeIdx === -1) return;

    const newIdx = direction === "left" ? activeIdx + 1 : activeIdx - 1;
    const next = triggers[newIdx];
    if (!next) return;

    // reka-ui TabsTrigger uses @mousedown.left to switch tabs (not @click),
    // so dispatch mousedown first. Click after so consumer @click handlers
    // (e.g. scroll-to-top) still fire.
    next.dispatchEvent(
      new MouseEvent("mousedown", {
        bubbles: true,
        cancelable: true,
        button: 0,
      }),
    );
    next.click();
  },
});
</script>

<template>
  <TabsRoot ref="tabsRootInstance" v-bind="forwarded" data-slot="tabs">
    <slot />
  </TabsRoot>
</template>
