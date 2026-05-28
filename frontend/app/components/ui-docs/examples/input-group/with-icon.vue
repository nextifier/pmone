<script setup>
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
</script>

<template>
  <InputGroup class="w-full max-w-xs">
    <InputGroupAddon>
      <Icon name="lucide:search" />
    </InputGroupAddon>
    <InputGroupInput placeholder="Search" />
  </InputGroup>
</template>
