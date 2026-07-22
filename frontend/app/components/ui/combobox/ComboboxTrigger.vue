<script setup lang="ts">
import type { ComboboxTriggerProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { ChevronDown } from "lucide-vue-next"
import { ComboboxTrigger, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<ComboboxTriggerProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")

const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <ComboboxTrigger
    data-slot="combobox-trigger"
    v-bind="forwarded"
    :class="cn('cn-combobox-trigger', props.class)"
    tabindex="0"
  >
    <slot />
    <!-- Skipped under as-child: reka's Primitive needs a single root child, so a
         custom trigger (a Button, say) brings its own affordance. -->
    <ChevronDown v-if="!props.asChild" class="cn-combobox-trigger-icon pointer-events-none" />
  </ComboboxTrigger>
</template>
