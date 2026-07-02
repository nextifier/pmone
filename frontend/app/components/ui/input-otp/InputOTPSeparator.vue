<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { MinusIcon } from "lucide-vue-next"
import { useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<{ class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <div
    data-slot="input-otp-separator"
    role="separator"
    v-bind="forwarded"
    :class="cn('cn-input-otp-separator flex items-center', props.class)"
  >
    <slot>
      <MinusIcon />
    </slot>
  </div>
</template>
