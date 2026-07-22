<script setup lang="ts">
import type { ComboboxRootEmits, ComboboxRootProps } from "reka-ui"
import { reactiveOmit } from "@vueuse/core"
import { ComboboxRoot, useForwardPropsEmits } from "reka-ui"
import { nextTick, ref, watch } from "vue"

// Reka defaults `openOnClick` to false, so clicking the field would never open the
// list — only the chevron or typing would. Base UI (what ui.shadcn.com runs) opens on
// click, and a chips combobox has no chevron at all, so default it on. Still
// overridable per instance.
const props = withDefaults(defineProps<ComboboxRootProps & {
  /**
   * Highlight the first item on every filter change, matching Base UI's
   * `autoHighlight`. Reka only highlights when the list goes from empty to
   * non-empty, so the remaining keystrokes are wired here.
   */
  autoHighlight?: boolean
}>(), {
  openOnClick: true,
  autoHighlight: false,
})
const emits = defineEmits<ComboboxRootEmits>()

const delegatedProps = reactiveOmit(props, "autoHighlight")
const forwarded = useForwardPropsEmits(delegatedProps, emits)

const rootRef = ref<InstanceType<typeof ComboboxRoot> | null>(null)

function highlightFirstItem() {
  rootRef.value?.highlightFirstItem?.()
}

watch(
  () => (props.autoHighlight ? rootRef.value?.filtered?.count : undefined),
  (count) => {
    if (props.autoHighlight && count)
      nextTick(highlightFirstItem)
  },
)

defineExpose({
  highlightFirstItem,
  highlightSelected: () => rootRef.value?.highlightSelected?.(),
})
</script>

<template>
  <ComboboxRoot
    ref="rootRef"
    v-slot="slotProps"
    data-slot="combobox"
    v-bind="forwarded"
  >
    <slot v-bind="slotProps" />
  </ComboboxRoot>
</template>
