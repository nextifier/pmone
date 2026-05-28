<script setup>
import {
  ResizableHandle,
  ResizablePanel,
  ResizablePanelGroup,
} from "@/components/ui/resizable";
</script>

<template>
  <ResizablePanelGroup direction="vertical" class="h-64 w-full max-w-md rounded-lg border">
    <ResizablePanel :default-size="40">
      <div class="flex h-full items-center justify-center p-6 text-sm tracking-tight">Top</div>
    </ResizablePanel>
    <ResizableHandle with-handle />
    <ResizablePanel :default-size="60">
      <div class="flex h-full items-center justify-center p-6 text-sm tracking-tight">Bottom</div>
    </ResizablePanel>
  </ResizablePanelGroup>
</template>
