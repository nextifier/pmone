<script setup>
import { ref } from "vue";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";

const tags = ref(["one"]);
const space = " ";
</script>

<template>
  <TagsInput v-model="tags" :delimiter="space" class="w-full max-w-sm">
    <TagsInputItem v-for="t in tags" :key="t" :value="t">
      <TagsInputItemText />
      <TagsInputItemDelete />
    </TagsInputItem>
    <TagsInputInput placeholder="Type then press space" />
  </TagsInput>
</template>
