<script setup>
import {
  Drawer,
  DrawerContent,
  DrawerDescription,
  DrawerFooter,
  DrawerHeader,
  DrawerTitle,
  DrawerTrigger,
  DrawerClose,
} from "@/components/ui/drawer";
import { Button } from "@/components/ui/button";

const items = Array.from({ length: 16 }, (_, i) => `Item ${i + 1}`);
</script>

<template>
  <Drawer>
    <DrawerTrigger as-child>
      <Button variant="outline">View terms</Button>
    </DrawerTrigger>
    <DrawerContent>
      <DrawerHeader>
        <DrawerTitle>Terms of service</DrawerTitle>
        <DrawerDescription>Scroll to read the full list.</DrawerDescription>
      </DrawerHeader>
      <div class="max-h-72 overflow-y-auto px-4">
        <ul class="space-y-2 text-sm tracking-tight">
          <li v-for="item in items" :key="item" class="bg-muted rounded-md px-3 py-2">
            {{ item }}
          </li>
        </ul>
      </div>
      <DrawerFooter>
        <DrawerClose as-child>
          <Button>Accept</Button>
        </DrawerClose>
      </DrawerFooter>
    </DrawerContent>
  </Drawer>
</template>
