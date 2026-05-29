<script setup>
import { ref } from "vue";
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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const name = ref("Olivia Martin");
const email = ref("olivia@acme.com");
</script>

<template>
  <Drawer>
    <DrawerTrigger as-child>
      <Button variant="outline">Edit profile</Button>
    </DrawerTrigger>
    <DrawerContent>
      <DrawerHeader>
        <DrawerTitle>Edit profile</DrawerTitle>
        <DrawerDescription>Update your details and save.</DrawerDescription>
      </DrawerHeader>
      <div class="space-y-3 px-4 pb-2">
        <div class="space-y-1.5">
          <Label for="drawer-name">Name</Label>
          <Input id="drawer-name" v-model="name" />
        </div>
        <div class="space-y-1.5">
          <Label for="drawer-email">Email</Label>
          <Input id="drawer-email" v-model="email" type="email" />
        </div>
      </div>
      <DrawerFooter>
        <Button>Save</Button>
        <DrawerClose as-child>
          <Button variant="outline">Cancel</Button>
        </DrawerClose>
      </DrawerFooter>
    </DrawerContent>
  </Drawer>
</template>
