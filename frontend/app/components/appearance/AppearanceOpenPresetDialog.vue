<script setup lang="ts">
import { ref, watch } from "vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

const props = defineProps<{
  open: boolean;
  /** Returns true when the preset applied (dialog closes); false keeps it open. */
  onApply: (input: string) => boolean;
}>();

const emit = defineEmits<{ (e: "update:open", value: boolean): void }>();

const input = ref("");

watch(
  () => props.open,
  (open) => {
    if (!open) {
      input.value = "";
    }
  },
);

function setOpen(value: boolean) {
  emit("update:open", value);
}

function submit() {
  if (!input.value.trim()) {
    return;
  }
  if (props.onApply(input.value)) {
    setOpen(false);
  }
}
</script>

<template>
  <!-- DialogResponsive: modal on desktop, drawer on mobile (STYLE_GUIDE §10). -->
  <DialogResponsive :open="open" dialog-max-width="420px" @update:open="setOpen">
    <template #default>
      <form class="flex flex-col gap-4 px-4 pb-10 md:px-6 md:py-5" @submit.prevent="submit">
        <div>
          <div class="text-foreground text-lg font-semibold tracking-tighter text-balance">
            Open Preset
          </div>
          <p class="text-body mt-1.5 text-sm tracking-tight">
            Paste a preset code to load a saved configuration.
          </p>
        </div>
        <Input
          v-model="input"
          placeholder="e.g. --preset AQABAgAAAA"
          autocapitalize="none"
          autocorrect="off"
          spellcheck="false"
          class="h-10 md:h-9"
          @keydown.enter.prevent="submit"
        />
        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="setOpen(false)">Cancel</Button>
          <Button type="submit" :disabled="!input.trim()">Open</Button>
        </div>
      </form>
    </template>
  </DialogResponsive>
</template>
