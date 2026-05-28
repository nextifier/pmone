<script setup>
import { ref } from "vue";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";

const open = ref(false);
</script>

<template>
  <Button variant="outline" @click="open = true">Open programmatically</Button>
  <Dialog v-model:open="open">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>Controlled dialog</DialogTitle>
        <DialogDescription>
          The open state lives outside the dialog and is bound with v-model. Use this when the
          dialog opens as a result of a non-button event, like an async response.
        </DialogDescription>
      </DialogHeader>
    </DialogContent>
  </Dialog>
</template>
