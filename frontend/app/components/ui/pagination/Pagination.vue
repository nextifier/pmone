<script setup lang="ts">
import type { PaginationRootEmits, PaginationRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { PaginationRoot, useForwardPropsEmits } from "reka-ui"
import { Button } from "@/components/ui/button"
import { ButtonGroup } from "@/components/ui/button-group"
import { cn } from "@/lib/utils"

const props = withDefaults(
  defineProps<PaginationRootProps & { class?: HTMLAttributes["class"] }>(),
  {
    itemsPerPage: 10,
    siblingCount: 1,
  },
)
const emits = defineEmits<PaginationRootEmits>()

const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardPropsEmits(delegatedProps, emits)

// --- Batteries-included default pager (used when no compositional slot is given).
// Renders the numbered First/Prev/…/Next/Last ButtonGroup — the default look.
type PaginationEntry =
  | { type: "page"; value: number; key: string }
  | { type: "ellipsis"; key: string }

const currentPage = computed(() => props.page ?? props.defaultPage ?? 1)
const pageCount = computed(() =>
  Math.max(1, Math.ceil((props.total ?? 0) / (props.itemsPerPage || 10))),
)

const entries = computed<PaginationEntry[]>(() => {
  const total = pageCount.value
  const page = currentPage.value
  const sibling = props.siblingCount ?? 1
  const totalNumbers = sibling * 2 + 5

  if (total <= totalNumbers) {
    return Array.from({ length: total }, (_, i) => ({
      type: "page" as const,
      value: i + 1,
      key: `p${i + 1}`,
    }))
  }

  const left = Math.max(page - sibling, 1)
  const right = Math.min(page + sibling, total)
  const showLeftEllipsis = left > 2
  const showRightEllipsis = right < total - 1

  const result: PaginationEntry[] = [{ type: "page", value: 1, key: "p1" }]
  if (showLeftEllipsis) result.push({ type: "ellipsis", key: "el" })

  const start = showLeftEllipsis ? left : 2
  const end = showRightEllipsis ? right : total - 1
  for (let i = start; i <= end; i++) {
    result.push({ type: "page", value: i, key: `p${i}` })
  }

  if (showRightEllipsis) result.push({ type: "ellipsis", key: "er" })
  result.push({ type: "page", value: total, key: `p${total}` })
  return result
})

function go(p: number) {
  const clamped = Math.min(Math.max(p, 1), pageCount.value)
  if (clamped === currentPage.value) return
  emits("update:page", clamped)
}
</script>

<template>
  <!-- Compositional API (PaginationContent / PaginationItem / …) -->
  <PaginationRoot
    v-if="$slots.default"
    v-slot="slotProps"
    data-slot="pagination"
    v-bind="forwarded"
    :class="cn('cn-pagination mx-auto flex w-full justify-center', props.class)"
  >
    <slot v-bind="slotProps" />
  </PaginationRoot>

  <!-- Default look: numbered ButtonGroup pager -->
  <ButtonGroup v-else data-slot="pagination" :class="cn('mx-auto w-fit', props.class)">
    <Button
      variant="outline"
      size="icon"
      class="xs:flex hidden"
      :disabled="currentPage === 1"
      aria-label="First page"
      @click="go(1)"
    >
      <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
    </Button>
    <Button
      variant="outline"
      size="icon"
      :disabled="currentPage === 1"
      aria-label="Previous page"
      @click="go(currentPage - 1)"
    >
      <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
    </Button>

    <template v-for="entry in entries" :key="entry.key">
      <Button
        v-if="entry.type === 'page'"
        :variant="entry.value === currentPage ? 'default' : 'outline'"
        size="icon"
        :aria-current="entry.value === currentPage ? 'page' : undefined"
        @click="go(entry.value)"
      >
        {{ entry.value }}
      </Button>
      <Button
        v-else
        variant="outline"
        size="icon"
        disabled
        aria-hidden="true"
        tabindex="-1"
      >
        <Icon name="lucide:ellipsis" class="size-4 shrink-0" />
      </Button>
    </template>

    <Button
      variant="outline"
      size="icon"
      :disabled="currentPage === pageCount"
      aria-label="Next page"
      @click="go(currentPage + 1)"
    >
      <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
    </Button>
    <Button
      variant="outline"
      size="icon"
      class="xs:flex hidden"
      :disabled="currentPage === pageCount"
      aria-label="Last page"
      @click="go(pageCount)"
    >
      <Icon name="lucide:chevron-last" class="size-4 shrink-0" />
    </Button>
  </ButtonGroup>
</template>
