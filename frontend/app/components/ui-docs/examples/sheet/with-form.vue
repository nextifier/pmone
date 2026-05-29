<script setup>
import { ref } from "vue";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
  SheetClose,
} from "@/components/ui/sheet";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const name = ref("Olivia Martin");
const username = ref("olivia");
</script>

<template>
  <Sheet>
    <SheetTrigger as-child>
      <Button variant="outline">Edit profile</Button>
    </SheetTrigger>
    <SheetContent>
      <SheetHeader>
        <SheetTitle>Edit profile</SheetTitle>
        <SheetDescription>Update your details and save.</SheetDescription>
      </SheetHeader>
      <div class="space-y-3 p-4">
        <div class="space-y-1.5">
          <Label for="sheet-name">Name</Label>
          <Input id="sheet-name" v-model="name" />
        </div>
        <div class="space-y-1.5">
          <Label for="sheet-username">Username</Label>
          <Input id="sheet-username" v-model="username" />
        </div>
      </div>
      <SheetFooter>
        <SheetClose as-child>
          <Button>Save changes</Button>
        </SheetClose>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>
