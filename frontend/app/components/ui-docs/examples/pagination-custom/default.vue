<script setup>
import { ref } from "vue";
import { PaginationCustom } from "@/components/ui/pagination-custom";

const page = ref(3);
</script>

<template>
  <PaginationCustom v-model="page" :total="200" :per-page="10" />
</template>
