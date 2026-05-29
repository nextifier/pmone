<script setup>
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Collapsible disabled class="w-full max-w-sm space-y-2">
    <div class="flex items-center justify-between gap-2">
      <h4 class="text-sm font-semibold tracking-tight">Locked section</h4>
      <CollapsibleTrigger as-child>
        <Button variant="ghost" size="iconSm">
          <Icon name="lucide:chevrons-up-down" />
        </Button>
      </CollapsibleTrigger>
    </div>
    <CollapsibleContent class="space-y-2">
      <div class="bg-muted rounded-md px-4 py-3 text-sm tracking-tight">Hidden content</div>
    </CollapsibleContent>
  </Collapsible>
</template>
