<script setup lang="ts">
import type { DialogContentEmits, DialogContentProps } from "reka-ui"
import type { ComponentPublicInstance, HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { X } from "@lucide/vue"
import {
  DialogClose,
  DialogContent,

  DialogPortal,
  injectDialogRootContext,
  useForwardPropsEmits,
} from "reka-ui"
import { onMounted, onUpdated, ref, shallowRef, toRef, unref, watch } from "vue"
import { cn } from "@/lib/utils"
import SheetOverlay from "./SheetOverlay.vue"
import { useSwipeDismiss } from "./useSwipeDismiss"

interface SheetContentProps extends DialogContentProps {
  class?: HTMLAttributes["class"]
  side?: "top" | "right" | "bottom" | "left"
  showCloseButton?: boolean
  /** Drag the panel toward `side` to dismiss it. Add `data-sheet-swipe-ignore` to opt a subtree out. */
  swipeDismissible?: boolean
}

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<SheetContentProps>(), {
  side: "right",
  showCloseButton: true,
  swipeDismissible: true,
})
const emits = defineEmits<DialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class", "side", "showCloseButton", "swipeDismissible")

const forwarded = useForwardPropsEmits(delegatedProps, emits)

const contentRef = ref<ComponentPublicInstance | null>(null)
const overlayRef = ref<ComponentPublicInstance | null>(null)
const rootContext = injectDialogRootContext()

/**
 * reka-ui exposes `$el` as a plain getter, so it is not reactive. Worse, the
 * template ref lands on `DialogContent` while the sheet is still closed — at that
 * point `$el` is the placeholder comment `Presence` renders, and it never changes
 * identity afterwards. Only the open state tells us when the real node exists, so
 * resolve against that too, after the DOM has been committed.
 */
function useExposedElement(source: typeof contentRef) {
  const element = shallowRef<HTMLElement | null>(null)
  const sync = () => {
    const next = source.value?.$el as HTMLElement | null | undefined
    element.value = next?.nodeType === 1 ? next : null
  }
  watch([source, () => unref(rootContext.open)], sync, { flush: "post" })
  onMounted(sync)
  onUpdated(sync)
  return element
}

// The overlay is a sibling of the panel inside the portal, so the swipe variables
// have to be written to both — they do not inherit from one to the other.
useSwipeDismiss({
  enabled: toRef(() => props.swipeDismissible),
  element: useExposedElement(contentRef),
  overlay: useExposedElement(overlayRef),
  side: toRef(() => props.side),
  onDismiss: () => rootContext.onOpenChange(false),
})
</script>

<template>
  <DialogPortal>
    <SheetOverlay ref="overlayRef" />
    <DialogContent
      ref="contentRef"
      data-slot="sheet-content"
      :data-side="side"
      :class="cn('cn-sheet-content ease-(--panel-ease) data-open:animate-in data-open:duration-(--panel-open-dur) data-[side=bottom]:data-open:slide-in-from-bottom data-[side=left]:data-open:slide-in-from-left data-[side=right]:data-open:slide-in-from-right data-[side=top]:data-open:slide-in-from-top data-closed:animate-out data-closed:duration-[calc(var(--sheet-swipe-strength,1)*var(--panel-close-dur))] data-[side=bottom]:data-closed:slide-out-to-bottom data-[side=left]:data-closed:slide-out-to-left data-[side=right]:data-closed:slide-out-to-right data-[side=top]:data-closed:slide-out-to-top motion-reduce:animate-none!', props.class)"
      v-bind="{ ...forwarded, ...$attrs }"
    >
      <slot />

      <DialogClose
        v-if="showCloseButton"
        data-slot="sheet-close"
        class="cn-sheet-close ring-offset-background focus:ring-ring data-[state=open]:bg-secondary cursor-pointer rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:ring-2 focus:ring-offset-2 focus:outline-hidden"
      >
        <X class="size-4" />
        <span class="sr-only">Close</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
