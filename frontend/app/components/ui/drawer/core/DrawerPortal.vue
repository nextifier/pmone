<script lang="ts">
import type { TeleportProps } from "./teleport"

export interface DrawerPortalProps extends TeleportProps {}
</script>

<script setup lang="ts">
import { TeleportPrimitive } from "./teleport"

defineProps<DrawerPortalProps>()
</script>

<template>
  <TeleportPrimitive v-bind="$props">
    <slot />
  </TeleportPrimitive>
</template>
