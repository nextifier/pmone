<script setup>
import { ref } from "vue";
import { DialogResponsive } from "@/components/ui/dialog-responsive";
import { Button } from "@/components/ui/button";

const open = ref(false);

function handleClosePrevented() {
  alert("This dialog cannot be dismissed. Finish the task first.");
}
</script>

<template>
  <DialogResponsive
    v-model:open="open"
    prevent-close
    @close-prevented="handleClosePrevented"
  >
    <template #trigger>
      <Button variant="outline" @click="open = true">Open (prevent close)</Button>
    </template>
    <div class="p-6">
      <h2 class="text-lg font-semibold tracking-tighter">Confirmation required</h2>
      <p class="text-muted-foreground mt-2 text-sm tracking-tight">
        Clicking outside or pressing Escape will not close this dialog.
      </p>
      <Button class="mt-4" @click="open = false">Close manually</Button>
    </div>
  </DialogResponsive>
</template>
