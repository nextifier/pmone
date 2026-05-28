<script setup>
import { ref } from "vue";
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Input } from "@/components/ui/input";
import { Field, FieldLabel } from "@/components/ui/field";
import { Button } from "@/components/ui/button";

const open = ref(false);
const name = ref("");
</script>

<template>
  <DialogResponsive v-model:open="open" dialog-max-width="520px">
    <template #trigger>
      <Button @click="open = true">Edit profile</Button>
    </template>

    <div class="p-6">
      <h2 class="text-lg font-semibold tracking-tighter">Edit profile</h2>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Changes are saved when you press Save.
      </p>

      <div class="mt-6 space-y-4">
        <Field>
          <FieldLabel for="display-name">Display name</FieldLabel>
          <Input id="display-name" v-model="name" placeholder="Your name" />
        </Field>
      </div>

      <div class="mt-6 flex justify-end gap-x-2">
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button @click="open = false">Save</Button>
      </div>
    </div>
  </DialogResponsive>
</template>
