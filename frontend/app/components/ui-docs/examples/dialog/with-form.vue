<script setup>
import { ref } from "vue";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogClose,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel } from "@/components/ui/field";
import { Button } from "@/components/ui/button";

const name = ref("");
</script>

<template>
  <Dialog>
    <DialogTrigger as-child>
      <Button>New project</Button>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader>
        <DialogTitle>New project</DialogTitle>
        <DialogDescription>Give your project a name. You can change it later.</DialogDescription>
      </DialogHeader>
      <div class="space-y-4 py-2">
        <Field>
          <FieldLabel for="project-name">Project name</FieldLabel>
          <Input id="project-name" v-model="name" placeholder="Acme website" />
        </Field>
      </div>
      <DialogFooter>
        <DialogClose as-child>
          <Button variant="outline">Cancel</Button>
        </DialogClose>
        <Button>Create</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
