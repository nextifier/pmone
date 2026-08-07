<script lang="ts">
import type { PrimitiveProps } from "reka-ui"

export interface DrawerCloseProps extends PrimitiveProps {}
</script>

<script setup lang="ts">
import { Primitive } from "reka-ui"
import { useForwardExpose } from "reka-ui"
import { injectDrawerRootContext } from './DrawerRoot.vue'

const props = withDefaults(defineProps<DrawerCloseProps>(), { as: 'button' })
useForwardExpose()
const rootContext = injectDrawerRootContext()
</script>

<template>
  <Primitive
    v-bind="props"
    :type="as === 'button' ? 'button' : undefined"
    @click="rootContext.onOpenChange(false, 'close-press')"
  >
    <slot />
  </Primitive>
</template>
