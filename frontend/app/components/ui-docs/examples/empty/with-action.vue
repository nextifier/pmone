<script setup>
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Empty class="border-border w-full max-w-md border">
    <EmptyHeader>
      <EmptyMedia variant="icon">
        <Icon name="lucide:folder-plus" class="size-6" />
      </EmptyMedia>
      <EmptyTitle>No projects yet</EmptyTitle>
      <EmptyDescription>Create your first project or import an existing one.</EmptyDescription>
    </EmptyHeader>
    <EmptyContent class="flex flex-row gap-2">
      <Button>
        <Icon name="lucide:plus" class="size-4" />
        New project
      </Button>
      <Button variant="outline">Import</Button>
    </EmptyContent>
  </Empty>
</template>
