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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const open = ref(false);
const name = ref("Olivia Martin");
const email = ref("olivia@acme.com");
</script>

<template>
  <div>
    <Button variant="outline" @click="open = true">Edit profile</Button>
    <SlideDrawer v-model:open="open">
      <SlideDrawerHeader>
        <SlideDrawerTitle>Edit profile</SlideDrawerTitle>
        <SlideDrawerDescription>Update your details and save.</SlideDrawerDescription>
      </SlideDrawerHeader>
      <SlideDrawerBody>
        <div class="space-y-3">
          <div class="space-y-1.5">
            <Label for="slide-name">Name</Label>
            <Input id="slide-name" v-model="name" />
          </div>
          <div class="space-y-1.5">
            <Label for="slide-email">Email</Label>
            <Input id="slide-email" v-model="email" type="email" />
          </div>
        </div>
      </SlideDrawerBody>
      <SlideDrawerFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button @click="open = false">Save</Button>
      </SlideDrawerFooter>
    </SlideDrawer>
  </div>
</template>
