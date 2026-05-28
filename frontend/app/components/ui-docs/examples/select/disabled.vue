<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
</script>

<template>
  <Select disabled>
    <SelectTrigger class="w-[220px]">
      <SelectValue placeholder="Locked" />
    </SelectTrigger>
    <SelectContent>
      <SelectItem value="a">Option A</SelectItem>
    </SelectContent>
  </Select>
</template>
