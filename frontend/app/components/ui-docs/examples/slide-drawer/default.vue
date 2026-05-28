<script setup>
import { ref } from "vue";
import {
  SlideDrawer,
  SlideDrawerBody,
  SlideDrawerDescription,
  SlideDrawerFooter,
  SlideDrawerHeader,
  SlideDrawerTitle,
} from "@/components/ui/slide-drawer";
import { Button } from "@/components/ui/button";

const open = ref(false);
</script>

<template>
  <div>
    <Button variant="outline" @click="open = true">Open drawer</Button>
    <SlideDrawer v-model:open="open">
      <SlideDrawerHeader>
        <SlideDrawerTitle>Order details</SlideDrawerTitle>
        <SlideDrawerDescription>INV-001 · Olivia Martin</SlideDrawerDescription>
      </SlideDrawerHeader>
      <SlideDrawerBody>
        <p class="text-sm tracking-tight">Body scrolls; header and footer stay fixed.</p>
      </SlideDrawerBody>
      <SlideDrawerFooter>
        <Button @click="open = false">Close</Button>
      </SlideDrawerFooter>
    </SlideDrawer>
  </div>
</template>
