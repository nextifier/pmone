<script setup>
import { ref } from "vue";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";

const tags = ref(["vue", "nuxt"]);
</script>

<template>
  <TagsInput v-model="tags" disabled class="w-full max-w-sm">
    <TagsInputItem v-for="t in tags" :key="t" :value="t">
      <TagsInputItemText />
      <TagsInputItemDelete />
    </TagsInputItem>
    <TagsInputInput placeholder="Add tag" />
  </TagsInput>
</template>
