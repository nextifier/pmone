<script setup lang="ts">
import { cn } from "@/lib/utils";
import { InputGroupButton } from "@/components/ui/input-group";
import { X } from "lucide-vue-next";
import { ComboboxCancel } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = defineProps<{
  class?: HTMLAttributes["class"];
  disabled?: boolean;
}>();
</script>

<template>
  <InputGroupButton
    variant="ghost"
    size="icon-xs"
    as-child
    :disabled="props.disabled"
    :class="props.class"
  >
    <ComboboxCancel data-slot="combobox-clear" :class="cn('cn-combobox-clear')">
      <X class="cn-combobox-clear-icon pointer-events-none" />
    </ComboboxCancel>
  </InputGroupButton>
</template>
