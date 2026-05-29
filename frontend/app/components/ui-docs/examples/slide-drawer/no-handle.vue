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
    <Button variant="outline" @click="open = true">Open without handle</Button>
    <SlideDrawer v-model:open="open" :show-handle="false">
      <SlideDrawerHeader>
        <SlideDrawerTitle>Confirm action</SlideDrawerTitle>
        <SlideDrawerDescription>Set show-handle to false to hide the grabber.</SlideDrawerDescription>
      </SlideDrawerHeader>
      <SlideDrawerBody>
        <p class="text-sm tracking-tight">The drag handle at the top is hidden.</p>
      </SlideDrawerBody>
      <SlideDrawerFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button @click="open = false">Confirm</Button>
      </SlideDrawerFooter>
    </SlideDrawer>
  </div>
</template>
