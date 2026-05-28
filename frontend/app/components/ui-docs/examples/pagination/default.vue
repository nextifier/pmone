<script setup>
import { ref } from "vue";
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
import { Button } from "@/components/ui/button";

const page = ref(3);
</script>

<template>
  <Pagination v-model:page="page" :total="100" :items-per-page="10" :sibling-count="1" show-edges>
    <PaginationContent v-slot="{ items }">
      <PaginationFirst />
      <PaginationPrevious />
      <template v-for="(item, i) in items">
        <PaginationItem v-if="item.type === 'page'" :key="i" :value="item.value" as-child>
          <Button :variant="item.value === page ? 'default' : 'outline'" size="sm">
            {{ item.value }}
          </Button>
        </PaginationItem>
        <PaginationEllipsis v-else :key="`e-${i}`" :index="i" />
      </template>
      <PaginationNext />
      <PaginationLast />
    </PaginationContent>
  </Pagination>
</template>
