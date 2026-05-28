<script setup>
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Button } from "@/components/ui/button";
</script>

<template>
  <Collapsible class="w-full max-w-sm space-y-2">
    <div class="flex items-center justify-between gap-2">
      <h4 class="text-sm font-semibold tracking-tight">Show shipping details</h4>
      <CollapsibleTrigger as-child>
        <Button variant="ghost" size="iconSm">
          <Icon name="lucide:chevrons-up-down" />
        </Button>
      </CollapsibleTrigger>
    </div>
    <CollapsibleContent class="space-y-2">
      <div class="bg-muted rounded-md px-4 py-3 text-sm tracking-tight">Standard delivery</div>
      <div class="bg-muted rounded-md px-4 py-3 text-sm tracking-tight">Express delivery</div>
    </CollapsibleContent>
  </Collapsible>
</template>
