<script lang="ts">
import type { PrimitiveProps } from "reka-ui"

export interface DrawerDescriptionProps extends PrimitiveProps {}
</script>

<script setup lang="ts">
import { Primitive } from "reka-ui"
import { useForwardExpose } from "reka-ui"
import { injectDrawerRootContext } from './DrawerRoot.vue'

const props = withDefaults(defineProps<DrawerDescriptionProps>(), { as: 'p' })
useForwardExpose()
const rootContext = injectDrawerRootContext()
</script>

<template>
  <Primitive
    v-bind="props"
    :id="rootContext.descriptionId"
  >
    <slot />
  </Primitive>
</template>
