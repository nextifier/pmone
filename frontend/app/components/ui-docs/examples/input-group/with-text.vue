<script setup>
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
  InputGroupText,
} from "@/components/ui/input-group";
</script>

<template>
  <InputGroup class="w-full max-w-xs">
    <InputGroupAddon>
      <InputGroupText>$</InputGroupText>
    </InputGroupAddon>
    <InputGroupInput placeholder="0.00" />
    <InputGroupAddon align="end">
      <InputGroupText>USD</InputGroupText>
    </InputGroupAddon>
  </InputGroup>
</template>
