<script setup>
import { ref } from "vue";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const value = ref("");
</script>

<template>
  <Select v-model="value">
    <SelectTrigger class="w-[240px]">
      <SelectValue placeholder="Select a timezone" />
    </SelectTrigger>
    <SelectContent>
      <SelectGroup>
        <SelectLabel>North America</SelectLabel>
        <SelectItem value="pst">Pacific (PST)</SelectItem>
        <SelectItem value="mst">Mountain (MST)</SelectItem>
        <SelectItem value="cst">Central (CST)</SelectItem>
        <SelectItem value="est">Eastern (EST)</SelectItem>
      </SelectGroup>
      <SelectGroup>
        <SelectLabel>Europe</SelectLabel>
        <SelectItem value="gmt">London (GMT)</SelectItem>
        <SelectItem value="cet">Central European (CET)</SelectItem>
        <SelectItem value="eet">Eastern European (EET)</SelectItem>
      </SelectGroup>
    </SelectContent>
  </Select>
</template>
