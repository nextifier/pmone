<template>
  <ButtonGroup class="mx-auto w-fit">
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

    <template v-for="item in items" :key="item.key">
      <Button
        v-if="item.type === 'page'"
        :variant="item.value === currentPage ? 'default' : 'outline'"
        size="icon"
        :aria-current="item.value === currentPage ? 'page' : undefined"
        @click="go(item.value!)"
      >
        {{ item.value }}
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

<script setup lang="ts">
import { Button } from "@/components/ui/button";
import { ButtonGroup } from "@/components/ui/button-group";

type PaginationItem =
  | { type: "page"; value: number; key: string }
  | { type: "ellipsis"; key: string };

const currentPage = defineModel<number>("page", { default: 1 });

const props = withDefaults(
  defineProps<{
    total: number;
    itemsPerPage?: number;
    siblingCount?: number;
  }>(),
  {
    itemsPerPage: 10,
    siblingCount: 1,
  }
);

const pageCount = computed(() =>
  Math.max(1, Math.ceil(props.total / props.itemsPerPage))
);

const items = computed<PaginationItem[]>(() => {
  const total = pageCount.value;
  const page = currentPage.value;
  const sibling = props.siblingCount;
  const totalNumbers = sibling * 2 + 5;

  if (total <= totalNumbers) {
    return Array.from({ length: total }, (_, i) => ({
      type: "page" as const,
      value: i + 1,
      key: `p${i + 1}`,
    }));
  }

  const left = Math.max(page - sibling, 1);
  const right = Math.min(page + sibling, total);
  const showLeftEllipsis = left > 2;
  const showRightEllipsis = right < total - 1;

  const result: PaginationItem[] = [
    { type: "page", value: 1, key: "p1" },
  ];

  if (showLeftEllipsis) {
    result.push({ type: "ellipsis", key: "el" });
  }

  const start = showLeftEllipsis ? left : 2;
  const end = showRightEllipsis ? right : total - 1;
  for (let i = start; i <= end; i++) {
    result.push({ type: "page", value: i, key: `p${i}` });
  }

  if (showRightEllipsis) {
    result.push({ type: "ellipsis", key: "er" });
  }

  result.push({ type: "page", value: total, key: `p${total}` });
  return result;
});

function go(p: number) {
  const clamped = Math.min(Math.max(p, 1), pageCount.value);
  if (clamped === currentPage.value) return;
  currentPage.value = clamped;
}
</script>
