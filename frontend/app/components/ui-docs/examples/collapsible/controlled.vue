<script setup>
import { ref } from "vue";
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { Button } from "@/components/ui/button";

const open = ref(false);
</script>

<template>
  <div class="flex w-full max-w-sm flex-col gap-3">
    <Button variant="outline" @click="open = !open">
      {{ open ? "Hide" : "Show" }} advanced
    </Button>
    <Collapsible v-model:open="open">
      <CollapsibleContent>
        <div class="bg-muted mt-2 rounded-md p-4 text-sm tracking-tight">
          Tucked-away configuration that most users do not need.
        </div>
      </CollapsibleContent>
    </Collapsible>
  </div>
</template>
