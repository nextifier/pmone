<script lang="ts">
import type { PrimitiveProps } from "reka-ui"

export interface DrawerTitleProps extends PrimitiveProps {}
</script>

<script setup lang="ts">
import { Primitive } from "reka-ui"
import { useForwardExpose } from "reka-ui"
import { injectDrawerRootContext } from './DrawerRoot.vue'

const props = withDefaults(defineProps<DrawerTitleProps>(), { as: 'h2' })
const rootContext = injectDrawerRootContext()
useForwardExpose()
</script>

<template>
  <Primitive
    v-bind="props"
    :id="rootContext.titleId"
  >
    <slot />
  </Primitive>
</template>
