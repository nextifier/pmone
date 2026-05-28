<script setup>
import { ref } from "vue";
import { ButtonGroup } from "@/components/ui/button-group";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

const value = ref("");
</script>

<template>
  <ButtonGroup class="w-full max-w-sm">
    <Input v-model="value" placeholder="Search" />
    <Button>Go</Button>
  </ButtonGroup>
</template>
