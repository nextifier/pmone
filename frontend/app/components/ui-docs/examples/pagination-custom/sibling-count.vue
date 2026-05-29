<script setup>
import { ref } from "vue";
import { PaginationCustom } from "@/components/ui/pagination-custom";

const page = ref(6);
</script>

<template>
  <PaginationCustom v-model:page="page" :total="200" :items-per-page="10" :sibling-count="2" />
</template>
