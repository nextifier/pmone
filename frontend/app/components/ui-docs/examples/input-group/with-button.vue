<script setup>
import { ref } from "vue";
import {
  InputGroup,
  InputGroupButton,
  InputGroupInput,
} from "@/components/ui/input-group";

const v = ref("");
</script>

<template>
  <InputGroup class="w-full max-w-xs">
    <InputGroupInput v-model="v" placeholder="Invite by email" />
    <InputGroupButton variant="default" size="sm">Invite</InputGroupButton>
  </InputGroup>
</template>
