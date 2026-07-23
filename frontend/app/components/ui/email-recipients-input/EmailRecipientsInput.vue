<template>
  <div class="space-y-3">
    <div class="space-y-1">
      <Label>{{ label }}</Label>
      <p v-if="description" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
        {{ description }}
      </p>
    </div>

    <div v-if="model.length > 0" class="space-y-2">
      <div v-for="(email, index) in model" :key="index" class="flex items-center gap-1.5">
        <Input v-model="model[index]" type="email" placeholder="email@example.com" />
        <Button
          type="button"
          variant="ghost"
          size="icon"
          aria-label="Remove email"
          class="text-destructive hover:text-destructive/80 shrink-0"
          @click="remove(index)"
        >
          <Icon name="hugeicons:delete-01" class="size-4" />
        </Button>
      </div>
    </div>

    <button
      type="button"
      class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition-colors"
      @click="add"
    >
      <Icon name="hugeicons:add-01" class="size-4" />
      {{ addLabel }}
    </button>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

const model = defineModel({ type: Array, default: () => [] });

defineProps({
  label: { type: String, default: "" },
  description: { type: String, default: "" },
  addLabel: { type: String, default: "Add Email" },
});

const add = () => model.value.push("");
const remove = (index) => model.value.splice(index, 1);
</script>
