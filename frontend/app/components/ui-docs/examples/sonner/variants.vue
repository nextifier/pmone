<script setup>
import { toast } from "vue-sonner";
import { Button } from "@/components/ui/button";
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <Button variant="outline" @click="toast.success('Saved successfully')">Success</Button>
    <Button variant="outline" @click="toast.error('Something went wrong')">Error</Button>
    <Button variant="outline" @click="toast.info('Heads up')">Info</Button>
    <Button variant="outline" @click="toast.warning('Be careful')">Warning</Button>
  </div>
</template>
