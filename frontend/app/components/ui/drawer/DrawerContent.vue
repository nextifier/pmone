<script lang="ts" setup>
import type { DrawerContentEmits, DrawerContentProps } from "reka-ui";
import type { ComponentPublicInstance, HTMLAttributes, InjectionKey, Ref } from "vue";
import { reactiveOmit, useResizeObserver } from "@vueuse/core";
import {
  DrawerContent,
  DrawerPortal,
  DrawerViewport,
  injectDrawerRootContext,
  useForwardPropsEmits,
} from "reka-ui";
import {
  computed,
  inject,
  onMounted,
  onScopeDispose,
  onUpdated,
  provide,
  ref,
  shallowRef,
  useId,
  unref,
  watch,
} from "vue";
import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import DrawerBar from "./DrawerBar.vue";
import DrawerClose from "./DrawerClose.vue";
import DrawerOverlay from "./DrawerOverlay.vue";
import { useDrawerSwipeArbiter } from "./useDrawerSwipeArbiter";
import { useTransitionStatus } from "./useTransitionStatus";

type DrawerPosition = "top" | "right" | "bottom" | "left";

const SWIPE_DIRECTION_TO_POSITION = {
  up: "top",
  down: "bottom",
  left: "left",
  right: "right",
} as const;

const props = withDefaults(
  defineProps<
    DrawerContentProps & {
      class?: HTMLAttributes["class"];
      variant?: "default" | "straight" | "inset";
      showBar?: boolean;
      showCloseButton?: boolean;
      showOverlay?: boolean;
      /** Classes for the backdrop, e.g. to keep it mounted but invisible. */
      overlayClass?: HTMLAttributes["class"];
      /** Arbitrate drag against cross-axis scrolling. Turn off only to debug. */
      swipeArbitration?: boolean;
    }
  >(),
  {
    variant: "default",
    showBar: false,
    showCloseButton: false,
    showOverlay: true,
    swipeArbitration: true,
  }
);

const emits = defineEmits<DrawerContentEmits>();

// The root here is `DrawerPortal`, which cannot carry attributes, so fall-through
// would silently drop everything a consumer passes (`id`, `aria-*`, `data-*`).
defineOptions({ inheritAttrs: false });

const delegatedProps = reactiveOmit(
  props,
  "class",
  "variant",
  "showBar",
  "showCloseButton",
  "showOverlay",
  "overlayClass",
  "swipeArbitration"
);
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const rootContext = injectDrawerRootContext();
const position = computed<DrawerPosition>(
  () => SWIPE_DIRECTION_TO_POSITION[rootContext.swipeDirection.value]
);
const open = computed(() => unref(rootContext.open));
/** With snap points the backdrop tracks the active stop, not just the live drag. */
const hasSnapPoints = computed(() => (unref(rootContext.snapPoints)?.length ?? 0) > 1);

/**
 * A drawer behind another one scales against `--drawer-frontmost-height`, the
 * height of the drawer in front of it. reka puts `notifyParentFrontmostHeight`
 * in its root context and then never calls it, so that variable is never written
 * and the stack falls back to each drawer's own height — the moment the two
 * differ the drawer behind sits in the wrong place. Base UI walks the height up
 * the whole chain (`DrawerRoot.tsx: onNestedFrontmostHeightChange`), so keep the
 * ancestor list ourselves and do the same.
 */
type DrawerRootLike = ReturnType<typeof injectDrawerRootContext>;
const DRAWER_ANCESTORS = Symbol.for("ui.drawer.ancestors") as InjectionKey<DrawerRootLike[]>;
const ancestorRoots = inject(DRAWER_ANCESTORS, [] as DrawerRootLike[]);
provide(DRAWER_ANCESTORS, [...ancestorRoots, rootContext]);

function reportFrontmostHeight(height: number) {
  for (const ancestor of ancestorRoots) {
    ancestor.onNestedFrontmostHeightChange?.(height);
  }
}

/**
 * `--nested-drawers` is how far back a drawer sits in the stack, and each step
 * costs it a scale step and a peek offset. reka counts it, but forwards the
 * presence to `parentContext.notifyParentHasNestedDrawer` — the *grandparent's*
 * notifier rather than the parent's handler — so the count never travels past
 * the direct parent and two drawers behind end up stacked in exactly the same
 * spot. The direct parent is already covered by reka, so top up the rest.
 */
const olderAncestors = ancestorRoots.slice(0, -1);

/**
 * reka-ui exposes `$el` as a plain getter, so it is not reactive, and the
 * template ref lands while the drawer is still closed — at that point `$el` is a
 * placeholder comment and it never changes identity afterwards. Resolve after
 * every commit instead.
 */
function useExposedElement(source: Ref<ComponentPublicInstance | null>) {
  const element = shallowRef<HTMLElement | null>(null);
  const sync = () => {
    const next = source.value?.$el as HTMLElement | null | undefined;
    element.value = next?.nodeType === 1 ? next : null;
  };
  watch([source, open], sync, { flush: "post" });
  onMounted(sync);
  onUpdated(sync);
  return element;
}

const contentRef = ref<ComponentPublicInstance | null>(null);
const contentElement = useExposedElement(contentRef);

/**
 * coss styles the drawer against Base UI's transition model, so presence is ours
 * to own: reka's `Presence` ends the exit on `animationend`, which never fires
 * for a CSS transition. `forceMount` keeps reka rendering while `mounted` is
 * true, and the composable holds the element until its animations settle.
 */
const { mounted, transitionStatus } = useTransitionStatus({ open, element: contentElement });

/**
 * The backdrop sits behind two wrappers, and a `ref` on the outer one resolves to
 * a component instance whose `$el` never becomes the element. Stamp it with an id
 * instead and look it up once it is in the document.
 */
const overlayId = useId();
const overlayElement = shallowRef<HTMLElement | null>(null);
function resolveOverlay(): HTMLElement | null {
  if (!overlayElement.value?.isConnected) {
    overlayElement.value = document.querySelector<HTMLElement>(
      `[data-drawer-overlay="${overlayId}"]`
    );
  }
  return overlayElement.value;
}
const startingStyle = computed(() => (transitionStatus.value === "starting" ? "" : undefined));
const endingStyle = computed(() => (transitionStatus.value === "ending" ? "" : undefined));

useDrawerSwipeArbiter({
  enabled: computed(() => props.swipeArbitration),
  element: contentElement,
  side: position,
});

useResizeObserver(contentElement, ([entry]) => {
  if (!entry || !ancestorRoots.length) {
    return;
  }
  const height = entry.borderBoxSize?.[0]?.blockSize ?? (entry.target as HTMLElement).offsetHeight;
  if (height > 0) {
    reportFrontmostHeight(height);
  }
});

// Presence follows `mounted` so the stack stays put while this drawer animates out.
watch(
  mounted,
  (isMounted) => {
    for (const ancestor of olderAncestors) {
      ancestor.onNestedDrawerPresenceChange?.(isMounted);
    }
  },
  { flush: "post" }
);

/**
 * The height, though, has to be handed back the moment this drawer starts to
 * close. Waiting for the exit animation leaves every ancestor stretched to a
 * drawer that is already sliding away, so the one behind sits lifted off the
 * edge with a strip of backdrop under it.
 */
watch(
  open,
  (isOpen) => {
    if (isOpen || !ancestorRoots.length) {
      return;
    }
    const nearest = ancestorRoots[ancestorRoots.length - 1];
    reportFrontmostHeight(nearest ? unref(nearest.popupHeight) : 0);
  },
  { flush: "post" }
);

/**
 * A swipe-dismiss deliberately leaves the movement variables where the finger
 * let go, so the exit can continue from there instead of snapping back to zero
 * first. reka never clears them afterwards and reuses the node on the next open.
 */
watch(
  [open, contentElement],
  ([isOpen, el]) => {
    if (!isOpen || !el) {
      return;
    }
    // reka stamps `data-swipe-dismissed` and never takes it off; with `forceMount`
    // the node is reused, so the next open would start out marked as dismissed.
    el.removeAttribute("data-swipe-dismissed");
    el.removeAttribute("data-swipe-dismiss");
    for (const name of [
      "--drawer-swipe-movement-x",
      "--drawer-swipe-movement-y",
      "--drawer-swipe-baseline-x",
      "--drawer-swipe-baseline-y",
      "--drawer-swipe-strength",
    ]) {
      el.style.removeProperty(name);
    }
  },
  { flush: "post" }
);

/**
 * reka registers `--drawer-swipe-progress` with `inherits: false` and writes it
 * once on the backdrop at mount, so the backdrop cannot see the live value on its
 * own. It also resets the store to 0 the moment a swipe ends — mirroring that
 * blindly snaps the backdrop to full opacity for a frame before the closing fade,
 * a dark flash right as the drawer leaves. So follow the store only while the
 * finger is down; on a snap-back ease it home, on a dismiss freeze the last value
 * and let the closing fade carry it to zero.
 */
const progressStore = rootContext.nestedSwipeProgressStore;
const writeProgress = (value: number) => {
  resolveOverlay()?.style.setProperty("--drawer-swipe-progress", `${value}`);
};
/**
 * The popup keeps its own copy, written by reka, and feeds `--stack-progress`,
 * which scales this drawer back once it gains a child. reka leaves the last
 * swiped value behind on a snap-back, so clear it ourselves.
 */
const clearPopupProgress = () => {
  contentElement.value?.style.setProperty("--drawer-swipe-progress", "0");
};
/**
 * Every drawer behind this one recovers its scale as this one is swiped away, so
 * they all need the live progress. reka forwards it with the same off-by-one as
 * the presence count, landing on the grandparent and skipping the parent, so
 * feed the whole chain here instead. `set` is idempotent, so the value reka
 * already delivered is simply written again.
 */
const broadcastProgress = (value: number) => {
  for (const ancestor of ancestorRoots) {
    ancestor.nestedSwipeProgressStore.set(value);
  }
};
onScopeDispose(
  progressStore.subscribe(() => {
    if (unref(rootContext.isSwiping) || hasSnapPoints.value) {
      const progress = progressStore.getSnapshot();
      writeProgress(progress);
      broadcastProgress(progress);
    }
  })
);
watch([() => unref(rootContext.isSwiping), mounted], ([swiping]) => {
  // Same off-by-one as the presence count: reka hands the swipe flag to the
  // grandparent's notifier, so the drawer directly behind never learns that the
  // one in front of it is being dragged.
  for (const ancestor of ancestorRoots) {
    ancestor.onNestedSwipingChange?.(Boolean(swiping));
  }
  const el = resolveOverlay();
  if (!el) {
    return;
  }
  if (swiping) {
    el.setAttribute("data-swiping", "");
    writeProgress(progressStore.getSnapshot());
    return;
  }
  el.removeAttribute("data-swiping");
  if (open.value) {
    // A drawer resting on a snap point keeps the backdrop at that stop's value;
    // snapping it back to 1 here is what made it flash bright on release.
    const settled = hasSnapPoints.value ? progressStore.getSnapshot() : 0;
    writeProgress(settled);
    clearPopupProgress();
    broadcastProgress(settled);
    // reka zeroes the movement variables on a snap-back, so the arbiter's
    // baseline has to go in the same recalc or the panel would settle a slop
    // distance past where it started. On a dismiss both are left alone so the
    // exit continues from wherever the finger let go.
    contentElement.value?.style.removeProperty("--drawer-swipe-baseline-x");
    contentElement.value?.style.removeProperty("--drawer-swipe-baseline-y");
  }
});

defineExpose({ contentElement, overlayElement });
</script>

<template>
  <!--
    Structure and classes copied from coss `apps/ui/registry/default/ui/drawer.tsx`
    (DrawerPopup / DrawerViewport): Portal > Backdrop > Viewport > Popup.
  -->
  <DrawerPortal v-if="mounted">
    <DrawerOverlay
      v-if="showOverlay"
      :data-drawer-overlay="overlayId"
      force-mount
      :class="overlayClass"
      :data-starting-style="startingStyle"
      :data-ending-style="endingStyle"
    />
    <DrawerViewport
      data-slot="drawer-viewport"
      :class="
        cn(
          'fixed inset-0 z-50 [--bleed:--spacing(12)] [--inset:0px]',
          'touch-none',
          position === 'bottom' && 'grid grid-rows-[1fr_auto] pt-12',
          position === 'top' && 'grid grid-rows-[auto_1fr] pb-12',
          position === 'left' && 'flex justify-start',
          position === 'right' && 'flex justify-end',
          variant === 'inset' && 'px-(--inset) sm:[--inset:--spacing(4)]',
          variant === 'inset' && position !== 'bottom' && 'pt-(--inset)',
          variant === 'inset' && position !== 'top' && 'pb-(--inset)'
        )
      "
    >
      <DrawerContent
        ref="contentRef"
        force-mount
        data-slot="drawer-popup"
        :data-side="position"
        :data-starting-style="startingStyle"
        :data-ending-style="endingStyle"
        :class="
          cn(
            'bg-popover text-popover-foreground relative flex max-h-full min-h-0 w-full min-w-0 flex-col not-dark:bg-clip-padding shadow-lg/5 outline-none transition-[transform,box-shadow,height,background-color] duration-450 ease-[cubic-bezier(0.32,0.72,0,1)] will-change-transform [--peek:calc(--spacing(6)-1px)] [--scale-base:calc(max(0,1-(var(--nested-drawers)*var(--stack-step))))] [--scale:clamp(0,calc(var(--scale-base)+(var(--stack-step)*var(--stack-progress))),1)] [--shrink:calc(1-var(--scale))] [--stack-peek-offset:max(0px,calc((var(--nested-drawers)-var(--stack-progress))*var(--peek)))] [--stack-progress:clamp(0,var(--drawer-swipe-progress),1)] [--stack-step:0.05] before:pointer-events-none before:absolute before:inset-0 before:shadow-[0_1px_--theme(--color-black/4%)] after:pointer-events-none after:absolute after:bg-popover data-swiping:transition-none! data-rubber-band:transition-none! data-swiping:select-none data-nested-drawer-open:overflow-hidden data-nested-drawer-open:bg-[color-mix(in_srgb,var(--popover),var(--color-black)_calc(2%*(var(--nested-drawers)-var(--stack-progress))))] data-ending-style:shadow-transparent data-starting-style:shadow-transparent data-ending-style:duration-[calc(var(--drawer-swipe-strength)*400ms)] dark:data-nested-drawer-open:bg-[color-mix(in_srgb,var(--popover),var(--color-black)_calc(6%*(var(--nested-drawers)-var(--stack-progress))))] dark:before:shadow-[0_-1px_--theme(--color-white/6%)]',
            'touch-none',
            position === 'bottom' &&
              'transform-[translateY(calc(var(--drawer-snap-point-offset)+var(--drawer-swipe-movement-y)-var(--drawer-swipe-baseline-y,0px)))] data-ending-style:transform-[translateY(calc(100%+env(safe-area-inset-bottom,0px)+var(--inset)))] data-starting-style:transform-[translateY(calc(100%+env(safe-area-inset-bottom,0px)+var(--inset)))] row-start-2 -mb-[max(0px,calc(var(--drawer-snap-point-offset,0px)+clamp(0,1,var(--drawer-snap-point-offset,0px)/1px)*var(--drawer-swipe-movement-y,0px)))] border-t pb-[max(0px,calc(env(safe-area-inset-bottom,0px)+var(--drawer-snap-point-offset,0px)+clamp(0,1,var(--drawer-snap-point-offset,0px)/1px)*var(--drawer-swipe-movement-y,0px)))] not-data-starting-style:not-data-ending-style:transition-[transform,box-shadow,height,background-color,margin,padding] after:inset-x-0 after:top-full after:h-(--bleed) data-nested-drawer-open:shadow-[0_var(--bleed)_0_0_var(--popover)] has-data-[slot=drawer-bar]:pt-2 data-ending-style:mb-0 data-starting-style:mb-0 data-ending-style:pb-0 data-starting-style:pb-0',
            position === 'top' &&
              'data-starting-style:transform-[translateY(calc(-100%-var(--inset)))] data-ending-style:transform-[translateY(calc(-100%-var(--inset)))] transform-[translateY(calc(var(--drawer-swipe-movement-y)-var(--drawer-swipe-baseline-y,0px)))] border-b after:inset-x-0 after:bottom-full after:h-(--bleed) data-nested-drawer-open:shadow-[0_calc(var(--bleed)*-1)_0_0_var(--popover)] has-data-[slot=drawer-bar]:pb-2',
            position === 'left' &&
              'data-starting-style:transform-[translateX(calc(-100%-var(--inset)))] data-ending-style:transform-[translateX(calc(-100%-var(--inset)))] transform-[translateX(calc(var(--drawer-swipe-movement-x)-var(--drawer-swipe-baseline-x,0px)))] w-[calc(100%-(--spacing(12)))] max-w-md border-e after:inset-y-0 after:end-full after:w-(--bleed) data-nested-drawer-open:shadow-[calc(var(--bleed)*-1)_0_0_0_var(--popover)] has-data-[slot=drawer-bar]:pe-2',
            position === 'right' &&
              'transform-[translateX(calc(var(--drawer-swipe-movement-x)-var(--drawer-swipe-baseline-x,0px)))] data-ending-style:transform-[translateX(calc(100%+var(--inset)))] data-starting-style:transform-[translateX(calc(100%+var(--inset)))] col-start-2 w-[calc(100%-(--spacing(12)))] max-w-md border-s after:inset-y-0 after:start-full after:w-(--bleed) data-nested-drawer-open:shadow-[var(--bleed)_0_0_0_var(--popover)] has-data-[slot=drawer-bar]:ps-2',
            variant !== 'straight' && [
              position === 'bottom' && 'rounded-t-2xl',
              position === 'top' &&
                'rounded-b-2xl **:data-[slot=drawer-footer]:rounded-b-[calc(var(--radius-2xl)-1px)]',
              position === 'left' &&
                'rounded-e-2xl **:data-[slot=drawer-footer]:rounded-ee-[calc(var(--radius-2xl)-1px)]',
              position === 'right' &&
                'rounded-s-2xl **:data-[slot=drawer-footer]:rounded-es-[calc(var(--radius-2xl)-1px)]',
            ],
            variant === 'default' && [
              position === 'bottom' && 'before:rounded-t-[calc(var(--radius-2xl)-1px)]',
              position === 'top' && 'before:rounded-b-[calc(var(--radius-2xl)-1px)]',
              position === 'left' && 'before:rounded-e-[calc(var(--radius-2xl)-1px)]',
              position === 'right' && 'before:rounded-s-[calc(var(--radius-2xl)-1px)]',
            ],
            variant === 'inset' &&
              'before:hidden sm:rounded-2xl sm:border sm:after:bg-transparent sm:before:rounded-[calc(var(--radius-2xl)-1px)] sm:**:data-[slot=drawer-footer]:rounded-b-[calc(var(--radius-2xl)-1px)]',
            variant === 'straight' && '[--stack-step:0]',
            (position === 'bottom' || position === 'top') &&
              'h-(--drawer-height,auto) [--height:max(0px,calc(var(--drawer-frontmost-height,var(--drawer-height))))] data-nested-drawer-open:h-(--height)',
            position === 'bottom' &&
              'data-nested-drawer-open:transform-[translateY(calc(var(--drawer-swipe-movement-y)-var(--stack-peek-offset)-(var(--shrink)*var(--height))))_scale(var(--scale))] origin-[50%_calc(100%-var(--inset))]',
            position === 'top' &&
              'data-nested-drawer-open:transform-[translateY(calc(var(--drawer-swipe-movement-y)+var(--stack-peek-offset)+(var(--shrink)*var(--height))))_scale(var(--scale))] origin-[50%_var(--inset)]',
            position === 'left' &&
              'data-nested-drawer-open:transform-[translateX(calc(var(--drawer-swipe-movement-x)+var(--stack-peek-offset)))_scale(var(--scale))] origin-right',
            position === 'right' &&
              'data-nested-drawer-open:transform-[translateX(calc(var(--drawer-swipe-movement-x)-var(--stack-peek-offset)))_scale(var(--scale))] origin-left',
            props.class
          )
        "
        v-bind="{ ...forwarded, ...$attrs }"
      >
        <slot />
        <DrawerClose v-if="showCloseButton" aria-label="Close" class="absolute end-2 top-2" as-child>
          <Button size="icon" variant="ghost">
            <Icon name="lucide:x" class="size-4" />
          </Button>
        </DrawerClose>
        <DrawerBar v-if="showBar" />
      </DrawerContent>
    </DrawerViewport>
  </DrawerPortal>
</template>
