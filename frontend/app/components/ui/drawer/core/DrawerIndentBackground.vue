<script lang="ts">
import type { PrimitiveProps } from "reka-ui"

export interface DrawerIndentBackgroundProps extends PrimitiveProps {}
</script>

<script setup lang="ts">
import { Primitive } from "reka-ui"
import { useForwardExpose } from "reka-ui"
import { injectDrawerProviderContext } from './DrawerProvider.vue'

const props = withDefaults(defineProps<DrawerIndentBackgroundProps>(), { as: 'div' })
const { forwardRef } = useForwardExpose()
const providerContext = injectDrawerProviderContext(null)
</script>

<template>
  <Primitive
    v-bind="props"
    :ref="forwardRef"
    :data-active="providerContext?.active.value ? '' : undefined"
    :data-inactive="!providerContext?.active.value ? '' : undefined"
  >
    <slot />
  </Primitive>
</template>
