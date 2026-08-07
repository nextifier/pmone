<script setup lang="ts">
import type { PrimitiveProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import type { ButtonVariants } from "."
import { Primitive } from "reka-ui"
import { NuxtLink } from "#components"
import { cn } from "@/lib/utils"
import { Spinner } from "@/components/ui/spinner"
import { buttonVariants } from "."

interface Props extends PrimitiveProps {
  variant?: ButtonVariants["variant"]
  size?: ButtonVariants["size"]
  class?: HTMLAttributes["class"]
  to?: string
  loading?: boolean
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  as: "button",
})

const isExternal = computed(() => props.to?.startsWith("http"))
const isDisabled = computed(() => Boolean(props.loading || props.disabled))
</script>

<template>
  <component
    :is="to ? NuxtLink : Primitive"
    :to="to || undefined"
    :as="to ? undefined : as"
    :as-child="to ? undefined : asChild"
    :target="isExternal ? '_blank' : undefined"
    :rel="isExternal ? 'noopener noreferrer' : undefined"
    :disabled="to ? undefined : isDisabled || undefined"
    :aria-disabled="isDisabled || undefined"
    data-slot="button"
    :data-variant="variant ?? 'default'"
    :data-size="size ?? 'default'"
    :data-loading="loading ? '' : undefined"
    :class="cn(buttonVariants({ variant, size }), props.class)"
  >
    <!--
      Only while loading, and never with `asChild` — that mode hands the slot's single
      child to Primitive, so a wrapper would become the button itself.

      `contents` keeps the slot's own children as direct flex items, so gap, padding
      and the button's width are exactly what they were. `visibility` is inherited,
      so hiding the wrapper hides bare text nodes too — which `text-transparent`
      would not, and which would also swallow the spinner's own colour.
    -->
    <template v-if="loading && !asChild">
      <span class="contents invisible"><slot /></span>
      <Spinner
        class="pointer-events-none absolute"
        data-slot="button-loading-indicator"
      />
    </template>
    <slot v-else />
  </component>
</template>
