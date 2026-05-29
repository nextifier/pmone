<script setup>
import { ref } from "vue";
import { PaginationCustom } from "@/components/ui/pagination-custom";

const page = ref(2);
</script>

<template>
  <PaginationCustom v-model:page="page" :total="30" :items-per-page="10" />
</template>
