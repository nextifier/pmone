<template>
  <div class="flex flex-wrap items-center justify-end gap-x-3 gap-y-2">
    <!-- Only for a past edition, where the live site resolves a different event
         and the switch would look broken: it saves, nothing visible changes. -->
    <p
      v-if="hint"
      class="text-muted-foreground min-w-0 flex-1 text-xs tracking-tight sm:text-sm"
    >
      {{ hint }}
    </p>

    <div class="flex shrink-0 items-center gap-x-2">
      <Switch
        :id="switchId"
        :model-value="modelValue"
        :disabled="!canEdit || pending"
        @update:model-value="$emit('update:modelValue', $event)"
      />
      <Label :for="switchId" class="cursor-pointer whitespace-nowrap">
        {{ label }}
      </Label>
    </div>

    <Button v-if="previewUrl" as-child variant="outline" size="sm" class="shrink-0">
      <a :href="previewUrl" target="_blank" rel="noopener noreferrer">
        <Icon name="hugeicons:globe-02" class="size-4 shrink-0" />
        <span>Preview</span>
      </a>
    </Button>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";

/**
 * Whether a section is live on the event's public website, as a compact control
 * that sits between the page header and the list it governs.
 *
 * The preview link always carries its bypass parameter, so it opens the real
 * page whichever way the switch is set.
 */
defineProps({
  modelValue: { type: Boolean, default: true },
  label: { type: String, required: true },
  hint: { type: String, default: "" },
  previewUrl: { type: String, default: null },
  pending: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);

const switchId = `website-visibility-${useId()}`;
</script>
