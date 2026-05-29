<script setup>
import { ref } from "vue";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Button } from "@/components/ui/button";

const open = ref(false);
</script>

<template>
  <div>
    <Button variant="outline" @click="open = true">Reset settings</Button>
    <AlertDialog v-model:open="open">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Reset to defaults?</AlertDialogTitle>
          <AlertDialogDescription>
            This restores every setting to its original value.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction>Reset</AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
