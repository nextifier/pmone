<script setup>
import { ref } from "vue";
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Button } from "@/components/ui/button";

const open = ref(false);
</script>

<template>
  <DialogResponsive v-model:open="open" dialog-max-width="480px">
    <template #trigger>
      <Button variant="outline" @click="open = true">Open responsive dialog</Button>
    </template>
    <div class="p-6">
      <h2 class="text-lg font-semibold tracking-tighter">Product details</h2>
      <p class="text-muted-foreground mt-2 text-sm tracking-tight">
        On desktop this renders as a centered dialog. On mobile it switches to a bottom drawer
        with a drag handle and swipe-to-dismiss.
      </p>
    </div>
  </DialogResponsive>
</template>
