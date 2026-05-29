<script setup>
import {
  ResizableHandle,
  ResizablePanel,
  ResizablePanelGroup,
} from "@/components/ui/resizable";
</script>

<template>
  <ResizablePanelGroup direction="horizontal" class="h-48 w-full max-w-md rounded-lg border">
    <ResizablePanel :default-size="25">
      <div class="flex h-full items-center justify-center p-6 text-sm tracking-tight">Sidebar</div>
    </ResizablePanel>
    <ResizableHandle with-handle />
    <ResizablePanel :default-size="75">
      <ResizablePanelGroup direction="vertical">
        <ResizablePanel :default-size="50">
          <div class="flex h-full items-center justify-center p-6 text-sm tracking-tight">Header</div>
        </ResizablePanel>
        <ResizableHandle with-handle />
        <ResizablePanel :default-size="50">
          <div class="flex h-full items-center justify-center p-6 text-sm tracking-tight">Content</div>
        </ResizablePanel>
      </ResizablePanelGroup>
    </ResizablePanel>
  </ResizablePanelGroup>
</template>
