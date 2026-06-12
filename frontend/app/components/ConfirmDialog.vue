<template>
  <DialogResponsive v-model:open="isOpen">
    <template v-if="$slots.trigger" #trigger="{ open }">
      <slot name="trigger" :open="open" />
    </template>
    <template #default>
      <div class="px-4 pb-10 md:px-6 md:py-5">
        <div class="text-primary text-lg font-semibold tracking-tighter text-balance">
          {{ title }}
        </div>
        <p class="text-body mt-1.5 text-sm tracking-tight">{{ description }}</p>
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" :disabled="pending" @click="isOpen = false">
            {{ cancelLabel }}
          </Button>
          <Button :variant="variant" :disabled="pending" @click="$emit('confirm')">
            <Spinner v-if="pending" class="size-4" />
            <span v-else>{{ confirmLabel }}</span>
          </Button>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import { useVModel } from "@vueuse/core";
import { Button } from "@/components/ui/button";

const props = defineProps({
  open: { type: Boolean, default: undefined },
  title: { type: String, default: "Are you sure?" },
  description: { type: String, required: true },
  confirmLabel: { type: String, default: "Confirm" },
  cancelLabel: { type: String, default: "Cancel" },
  variant: { type: String, default: "default" },
  pending: { type: Boolean, default: false },
});

const emit = defineEmits(["update:open", "confirm"]);

const isOpen = useVModel(props, "open", emit, { passive: true });
</script>
