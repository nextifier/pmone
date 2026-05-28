<script setup>
import {
  ResizableHandle,
  ResizablePanel,
  ResizablePanelGroup,
} from "@/components/ui/resizable";
</script>

<template>
  <ResizablePanelGroup direction="horizontal" class="h-48 w-full max-w-md rounded-lg border">
    <ResizablePanel :default-size="33">
      <div class="flex h-full items-center justify-center p-6 text-sm tracking-tight">One</div>
    </ResizablePanel>
    <ResizableHandle with-handle />
    <ResizablePanel :default-size="67">
      <div class="flex h-full items-center justify-center p-6 text-sm tracking-tight">Two</div>
    </ResizablePanel>
  </ResizablePanelGroup>
</template>
