<script lang="ts" setup>
import type { DrawerContentEmits, DrawerContentProps } from "./core";
import type { ComponentPublicInstance, HTMLAttributes, Ref } from "vue";
import { reactiveOmit } from "@vueuse/core";
import { DrawerContent, DrawerPortal, DrawerViewport, injectDrawerRootContext } from "./core";
import { useForwardPropsEmits } from "reka-ui";
import {
  computed,
  nextTick,
  onMounted,
  onScopeDispose,
  onUpdated,
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
import { useDrawerVirtualKeyboard } from "./useDrawerVirtualKeyboard";
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
      /**
       * Classes for the viewport — the fixed box the panel is laid out in.
       * Reach for it when the drawer must not start at the edge of the screen,
       * e.g. a menu that hangs below a sticky header.
       */
      viewportClass?: HTMLAttributes["class"];
      /** Arbitrate drag against cross-axis scrolling. Turn off only to debug. */
      swipeArbitration?: boolean;
      /** Lift the drawer clear of the software keyboard. */
      virtualKeyboard?: boolean;
    }
  >(),
  {
    variant: "default",
    showBar: false,
    showCloseButton: false,
    showOverlay: true,
    swipeArbitration: true,
    virtualKeyboard: true,
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
  "viewportClass",
  "swipeArbitration",
  "virtualKeyboard"
);
const forwarded = useForwardPropsEmits(delegatedProps, emits);

const rootContext = injectDrawerRootContext();
const position = computed<DrawerPosition>(
  () => SWIPE_DIRECTION_TO_POSITION[rootContext.swipeDirection.value]
);
const open = computed(() => unref(rootContext.open));

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

const { keyboardInset, keyboardVisible } = useDrawerVirtualKeyboard({
  enabled: computed(() => props.virtualKeyboard),
  active: mounted,
  element: contentElement,
});

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
 * The backdrop reads `--drawer-backdrop-progress` / `--drawer-backdrop-strength`
 * rather than reka's own variables, because reka rewrites those on the backdrop
 * on every render — see the comment in `DrawerOverlay.vue`. These two are ours
 * alone, so the value below is the only one the backdrop ever sees.
 */
const progressStore = rootContext.nestedSwipeProgressStore;
/**
 * Base UI keeps two separate numbers: `swipeProgress`, which drives this
 * drawer's own backdrop and is zeroed while the drawer has a child
 * (`isActive = open && !nested`), and `nestedSwipeProgress`, which travels up
 * the chain either way (`DrawerViewport.tsx:194-196`). reka collapses both into
 * one store, so without this a parent's backdrop fades out as its child is
 * swiped away.
 */
const backdropProgress = (value: number) => (unref(rootContext.hasNestedDrawer) ? 0 : value);
const writeProgress = (value: number): boolean => {
  const el = resolveOverlay();
  el?.style.setProperty("--drawer-backdrop-progress", `${value}`);
  return Boolean(el);
};
/**
 * reka scales the closing animation by `--drawer-swipe-strength` but only ever
 * writes it to the popup (`DrawerContentImpl.js` onRelease), so the backdrop's
 * exit always took the full 400ms no matter how fast the drawer was flicked.
 * Base UI writes it to both (`DrawerViewport.tsx:217-225`); mirror it here.
 */
const syncBackdropStrength = () => {
  const strength = contentElement.value?.style.getPropertyValue("--drawer-swipe-strength");
  resolveOverlay()?.style.setProperty("--drawer-backdrop-strength", strength || "1");
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
 * Follow the store the whole time the drawer is on screen, not just while the
 * finger is down: with snap points the resting value differs per stop, and the
 * patched `snapPointProgress` watcher pushes it here. The one moment to stop
 * following is the exit — reka zeroes the store as the drawer leaves, which
 * would snap the backdrop to full opacity for a frame right before it fades.
 * Base UI has the same reset and gets away with it because `data-ending-style`
 * pins the backdrop to `opacity: 0`; ours does too, but only once the ending
 * class has landed, so hold the last value until then.
 */
const flushProgress = () => {
  if (transitionStatus.value === "ending") {
    return;
  }
  return writeProgress(backdropProgress(progressStore.getSnapshot()));
};
const syncProgress = () => {
  // The portal teleports on a post-flush job of its own, so on the frame the
  // drawer opens the backdrop may not be in the document yet. Without a retry a
  // drawer reopening onto the snap point it already had would never get a value
  // at all, because the store never changes and so never notifies. The retry
  // re-reads the store rather than replaying the value from this call: the store
  // does move on in between, and writing the old number back is what made the
  // backdrop turn up dark on the second open.
  if (flushProgress() === false) {
    nextTick(flushProgress);
  }
};
onScopeDispose(progressStore.subscribe(syncProgress));
/** `subscribe` only fires on change, so hand the drawer its first value here. */
watch([mounted, open, () => unref(rootContext.hasNestedDrawer)], syncProgress, {
  flush: "post",
});
/**
 * reka leaves its own store holding whatever the finger last produced: `reset()`
 * zeroes the internal number without ever emitting it, and `finishSwipe()` does
 * not emit either. So a drawer that has been snapped back — or simply reopened
 * after an earlier swipe — starts life claiming to be part-way dismissed, and
 * every drawer behind it sits shrunk with its peek eaten. A settled drawer is at
 * its snap point's progress, or at 0 when it has no snap points; the first case
 * is already reka's watcher to own, so only the second needs saying.
 */
const settleProgress = () => {
  if ((unref(rootContext.snapPoints)?.length ?? 0) < 2) {
    progressStore.set(0);
  }
};
watch([open, () => unref(rootContext.isSwiping)], ([isOpen, swiping]) => {
  if (isOpen && !swiping) {
    settleProgress();
  }
});
/**
 * And the same thing from the other end, which is the half that actually holds:
 * the store is doing double duty, carrying this drawer's own swipe progress and
 * the progress of whichever drawer sits in front of it. Once the child is gone
 * the second meaning no longer applies, so the value it left behind has to go —
 * otherwise the drawer stays shrunk and its backdrop half faded. Base UI keeps
 * the two apart and calls `finishNestedSwipe()` for this
 * (`DrawerViewport.tsx:201-203`). Guarded on the transition so a drawer that
 * never had a child cannot clobber its own snap point value.
 */
watch(
  () => Boolean(unref(rootContext.hasNestedDrawer)),
  (hasChild, hadChild) => {
    if (hadChild && !hasChild) {
      progressStore.set(0);
    }
  },
  { flush: "post" }
);
watch([() => unref(rootContext.isSwiping), mounted], ([swiping]) => {
  const el = resolveOverlay();
  if (!el) {
    return;
  }
  if (swiping) {
    el.setAttribute("data-swiping", "");
    syncProgress();
    return;
  }
  el.removeAttribute("data-swiping");
  syncBackdropStrength();
  if (open.value) {
    // reka snaps to the nearest stop synchronously here but only pushes the new
    // progress from its own watcher, which runs later in this same flush; this
    // write uses the value we already have and the subscription corrects it
    // before the frame is painted.
    syncProgress();
    clearPopupProgress();
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
      :data-side="position"
      :data-keyboard-visible="keyboardVisible ? '' : undefined"
      :style="{ '--drawer-keyboard-inset': `${keyboardInset}px` }"
      :class="
        cn(
          'fixed inset-x-0 top-0 z-50 [--bleed:--spacing(12)] [--inset:0px] [--drawer-keyboard-inset:0px]',
          'touch-none',
          // Shortening the box from the bottom lifts the panel clear of the
          // keyboard and lowers its `max-h-full` ceiling in the same move. A
          // `top` drawer is anchored to the opposite edge, so it stays put.
          position === 'top' ? 'bottom-0' : 'bottom-(--drawer-keyboard-inset)',
          position === 'bottom' && 'grid grid-rows-[1fr_auto] pt-12',
          position === 'top' && 'grid grid-rows-[auto_1fr] pb-12',
          position === 'left' && 'flex justify-start',
          position === 'right' && 'flex justify-end',
          variant === 'inset' && 'px-(--inset) sm:[--inset:--spacing(4)]',
          variant === 'inset' && position !== 'bottom' && 'pt-(--inset)',
          variant === 'inset' && position !== 'top' && 'pb-(--inset)',
          viewportClass
        )
      "
    >
      <DrawerContent
        ref="contentRef"
        force-mount
        data-slot="drawer-popup"
        :data-side="position"
        :data-keyboard-visible="keyboardVisible ? '' : undefined"
        :data-starting-style="startingStyle"
        :data-ending-style="endingStyle"
        :class="
          cn(
            'bg-popover text-popover-foreground relative flex max-h-full min-h-0 w-full min-w-0 flex-col not-dark:bg-clip-padding shadow-lg/5 outline-none transition-[transform,box-shadow,height,background-color] duration-450 ease-[cubic-bezier(0.32,0.72,0,1)] will-change-transform [--peek:calc(--spacing(6)-1px)] [--scale-base:calc(max(0,1-(var(--nested-drawers)*var(--stack-step))))] [--scale:clamp(0,calc(var(--scale-base)+(var(--stack-step)*var(--stack-progress))),1)] [--shrink:calc(1-var(--scale))] [--stack-peek-offset:max(0px,calc((var(--nested-drawers)-var(--stack-progress))*var(--peek)))] [--stack-progress:clamp(0,var(--drawer-swipe-progress),1)] [--stack-step:0.05] before:pointer-events-none before:absolute before:inset-0 before:shadow-[0_1px_--theme(--color-black/4%)] after:pointer-events-none after:absolute after:bg-popover data-swiping:transition-none! data-rubber-band:transition-none! data-swiping:select-none motion-reduce:transition-none! data-nested-drawer-open:overflow-hidden data-nested-drawer-open:bg-[color-mix(in_srgb,var(--popover),var(--color-black)_calc(2%*(var(--nested-drawers)-var(--stack-progress))))] data-ending-style:shadow-transparent data-starting-style:shadow-transparent data-ending-style:duration-[calc(var(--drawer-swipe-strength)*400ms)] dark:data-nested-drawer-open:bg-[color-mix(in_srgb,var(--popover),var(--color-black)_calc(6%*(var(--nested-drawers)-var(--stack-progress))))] dark:before:shadow-[0_-1px_--theme(--color-white/6%)]',
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
            // Consumers usually cap the height in `vh` (`ResponsiveDialog` uses
            // `max-h-[85vh]`), which does not shrink when the keyboard appears,
            // so long content would still run underneath it. The variant wins on
            // specificity, and tailwind-merge keeps both because a different
            // variant is a different group. It also guarantees the popup always
            // has *some* ceiling, which is what keeps `--drawer-snap-point-offset`
            // -> `padding-bottom` -> measured border box from feeding itself.
            position !== 'top' && 'data-keyboard-visible:max-h-full',
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
