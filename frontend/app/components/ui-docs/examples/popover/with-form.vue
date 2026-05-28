<script setup>
import { ref } from "vue";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const width = ref("100%");
const height = ref("60vh");
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button variant="outline">Dimensions</Button>
    </PopoverTrigger>
    <PopoverContent class="w-72 space-y-3">
      <div class="space-y-1.5">
        <Label for="width">Width</Label>
        <Input id="width" v-model="width" />
      </div>
      <div class="space-y-1.5">
        <Label for="height">Height</Label>
        <Input id="height" v-model="height" />
      </div>
    </PopoverContent>
  </Popover>
</template>
