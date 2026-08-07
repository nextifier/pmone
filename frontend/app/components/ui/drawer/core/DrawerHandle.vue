<script lang="ts">
import type { PrimitiveProps } from "reka-ui"

export interface DrawerHandleProps extends PrimitiveProps {}
</script>

<script setup lang="ts">
import { Primitive } from "reka-ui"
import { useForwardExpose } from "reka-ui"
import { injectDrawerRootContext } from './DrawerRoot.vue'

const props = withDefaults(defineProps<DrawerHandleProps>(), { as: 'div' })
useForwardExpose()
const rootContext = injectDrawerRootContext()
</script>

<template>
  <Primitive
    v-bind="props"
    aria-hidden="true"
    :data-state="rootContext.open.value ? 'open' : 'closed'"
  >
    <slot />
  </Primitive>
</template>
