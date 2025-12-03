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
      class="text-muted-foreground flex w-full items-center justify-center-safe gap-0 -space-x-px overflow-auto text-sm *:shrink-0"
      v-slot="{ items }"
    >
      <PaginationFirst as-child>
        <button
          class="xs:flex border-border hover:text-foreground hidden size-9 items-center justify-center rounded-none border text-center shadow-none first:rounded-s-md last:rounded-e-md focus-visible:z-10 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
        >
          <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
        </button>
      </PaginationFirst>
      <PaginationPrevious as-child>
        <button
          class="border-border hover:text-foreground flex size-9 items-center justify-center rounded-none border text-center shadow-none focus-visible:z-10 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
        >
          <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
        </button>
      </PaginationPrevious>
      <template v-for="item in items">
        <PaginationItem v-if="item.type === 'page'" :value="item.value" asChild>
          <button
            class="border-border hover:text-foreground flex size-9 items-center justify-center rounded-none border text-center shadow-none focus-visible:z-10 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
            :class="{
              'bg-border/75 text-foreground': item.value === page,
            }"
          >
            {{ item.value }}
          </button>
        </PaginationItem>
        <PaginationEllipsis v-if="item.type === 'ellipsis'" as-child>
          <button
            class="border-border hover:text-foreground flex size-9 items-center justify-center rounded-none border text-center shadow-none focus-visible:z-10 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
          >
            <Icon name="lucide:ellipsis" class="size-4 shrink-0" />
          </button>
        </PaginationEllipsis>
      </template>
      <PaginationNext as-child>
        <button
          class="border-border hover:text-foreground flex size-9 items-center justify-center rounded-none border text-center shadow-none focus-visible:z-10 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
        >
          <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
        </button>
      </PaginationNext>
      <PaginationLast as-child>
        <button
          class="xs:flex border-border hover:text-foreground flex hidden size-9 items-center justify-center rounded-none border text-center shadow-none first:rounded-s-md last:rounded-e-md focus-visible:z-10 aria-disabled:pointer-events-none [&[aria-disabled]>svg]:opacity-50"
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
