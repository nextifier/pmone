<script setup>
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";

const sides = ["top", "right", "bottom", "left"];
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <Sheet v-for="side in sides" :key="side">
      <SheetTrigger as-child>
        <Button variant="outline">{{ side }}</Button>
      </SheetTrigger>
      <SheetContent :side="side">
        <SheetHeader>
          <SheetTitle>{{ side }}</SheetTitle>
        </SheetHeader>
      </SheetContent>
    </Sheet>
  </div>
</template>
