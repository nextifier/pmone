<template>
  <Pagination
    v-model:page="currentPage"
    :itemsPerPage="props.itemsPerPage"
    :total="props.total"
    v-slot="{ page }"
    :showEdges="true"
    :siblingCount="props.siblingCount"
  >
    <PaginationContent
      class="text-foreground flex w-full items-center justify-center-safe gap-x-0.5 overflow-auto rounded-full text-sm *:shrink-0 sm:gap-x-1"
      v-slot="{ items }"
    >
      <PaginationFirst as-child>
        <button
          class="border-border hover:bg-muted xs:flex hidden size-8 items-center justify-center rounded-lg text-center shadow-none transition focus-visible:z-10 active:scale-98 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
        >
          <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
        </button>
      </PaginationFirst>
      <PaginationPrevious as-child>
        <button
          class="border-border hover:bg-muted flex size-8 items-center justify-center rounded-lg text-center shadow-none transition focus-visible:z-10 active:scale-98 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
        >
          <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
        </button>
      </PaginationPrevious>
      <template v-for="item in items">
        <PaginationItem v-if="item.type === 'page'" :value="item.value" asChild>
          <button
            class="border-primary/20 hover:bg-muted flex size-8 items-center justify-center rounded-lg text-center shadow-none transition focus-visible:z-10 active:scale-98 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
            :class="{
              'border font-medium': item.value === page,
            }"
          >
            {{ item.value }}
          </button>
        </PaginationItem>
        <PaginationEllipsis v-if="item.type === 'ellipsis'" as-child>
          <button
            class="flex size-8 cursor-default items-center justify-center rounded-lg text-center shadow-none focus-visible:z-10 active:scale-98 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
          >
            <Icon name="lucide:ellipsis" class="size-4 shrink-0" />
          </button>
        </PaginationEllipsis>
      </template>
      <PaginationNext as-child>
        <button
          class="border-border hover:bg-muted flex size-8 items-center justify-center rounded-lg text-center shadow-none transition focus-visible:z-10 active:scale-98 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
        >
          <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
        </button>
      </PaginationNext>
      <PaginationLast as-child>
        <button
          class="border-border hover:bg-muted xs:flex hidden size-8 items-center justify-center rounded-lg text-center shadow-none transition focus-visible:z-10 active:scale-98 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
        >
          <Icon name="lucide:chevron-last" class="size-4 shrink-0" />
        </button>
      </PaginationLast>
    </PaginationContent>
  </Pagination>
</template>

<script setup lang="ts">
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationFirst,
  PaginationItem,
  PaginationLast,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";

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
</script>
