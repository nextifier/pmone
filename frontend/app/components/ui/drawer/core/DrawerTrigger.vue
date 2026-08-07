<script lang="ts">
import type { PrimitiveProps } from "reka-ui"

export interface DrawerTriggerProps extends PrimitiveProps {
  /**
   * Close the drawer when pressed while it is open, instead of only opening it.
   *
   * Off by default, matching Base UI: a trigger normally sits behind a modal
   * drawer and is inert, so a second press cannot reach it anyway. Turn it on
   * for a trigger that stays reachable and doubles as the close control — a menu
   * button in a sticky header that morphs into a cross, say. That one also needs
   * `pointer-events-auto`, since the scroll lock makes the body inert.
   */
  toggle?: boolean
}
</script>

<script setup lang="ts">
import { onUnmounted, watch } from 'vue'
import { Primitive } from "reka-ui"
import { useForwardExpose } from "reka-ui"
import { injectDrawerRootContext } from './DrawerRoot.vue'

const props = withDefaults(defineProps<DrawerTriggerProps>(), { as: 'button', toggle: false })
const rootContext = injectDrawerRootContext()
const { forwardRef, currentElement } = useForwardExpose()

// Keep triggerElement in sync with the rendered element — useForwardExpose's
// currentElement is a reactive computed ref, and v-if / conditional rendering
// can swap the underlying DOM node, so a one-shot onMounted assignment would
// go stale and break finalFocus restoration in DrawerContent.
watch(currentElement, (el) => {
  rootContext.triggerElement.value = el
}, { immediate: true })

onUnmounted(() => {
  if (rootContext.triggerElement.value === currentElement.value)
    rootContext.triggerElement.value = undefined
})

function onTriggerClick() {
  if (props.toggle && rootContext.open.value) {
    rootContext.onOpenChange(false, 'trigger-press')
    return
  }
  rootContext.onOpenChange(true, 'trigger-press')
}
</script>

<template>
  <Primitive
    v-bind="{ ...props, toggle: undefined }"
    :ref="forwardRef"
    :type="as === 'button' ? 'button' : undefined"
    aria-haspopup="dialog"
    :aria-expanded="rootContext.open.value"
    :aria-controls="rootContext.open.value ? rootContext.contentId : undefined"
    :data-state="rootContext.open.value ? 'open' : 'closed'"
    @click="onTriggerClick"
  >
    <slot />
  </Primitive>
</template>
