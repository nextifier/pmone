<script setup>
import { ref } from "vue";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationNext,
  PaginationPrevious,
} from "@/components/ui/pagination";
import { Button } from "@/components/ui/button";

const page = ref(1);
</script>

<template>
  <div class="flex flex-col items-center gap-3">
    <p class="text-sm tracking-tight text-muted-foreground">Current page: {{ page }}</p>
    <Pagination v-model:page="page" :total="50" :items-per-page="10" :sibling-count="1">
      <PaginationContent v-slot="{ items }">
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
      </PaginationContent>
    </Pagination>
  </div>
</template>
