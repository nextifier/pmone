<script setup lang="ts">
import type { DropdownMenuRootEmits, DropdownMenuRootProps } from "reka-ui"
import { DropdownMenuRoot, useForwardPropsEmits } from "reka-ui"

// Non-modal by default: reka's modal mode locks body scroll, which removes the
// page scrollbar and shifts the layout every time a menu opens.
const props = withDefaults(defineProps<DropdownMenuRootProps>(), {
  modal: false,
})
const emits = defineEmits<DropdownMenuRootEmits>()

const forwarded = useForwardPropsEmits(props, emits)
</script>

<template>
  <DropdownMenuRoot
    data-slot="dropdown-menu"
    v-bind="forwarded"
  >
    <slot />
  </DropdownMenuRoot>
</template>
