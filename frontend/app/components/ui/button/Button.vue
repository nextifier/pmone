<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "."
import { Primitive } from "reka-ui"
import { NuxtLink } from "#components"
import { cn } from "@/lib/utils"
import { buttonVariants } from "."

interface Props extends PrimitiveProps {
  variant?: ButtonVariants["variant"]
  size?: ButtonVariants["size"]
  class?: HTMLAttributes["class"]
  to?: string
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
})

const isExternal = computed(() => props.to?.startsWith("http"))
</script>

<template>
  <component
    :is="to ? NuxtLink : Primitive"
    :to="to || undefined"
    :as="to ? undefined : as"
    :as-child="to ? undefined : asChild"
    :target="isExternal ? '_blank' : undefined"
    :rel="isExternal ? 'noopener noreferrer' : undefined"
    data-slot="button"
    :data-variant="variant ?? 'default'"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <slot />
  </component>
</template>
