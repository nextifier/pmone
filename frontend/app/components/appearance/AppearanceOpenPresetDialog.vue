<script setup lang="ts">
import { ref, watch } from "vue";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
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
  <Dialog :open="open" @update:open="setOpen">
    <DialogContent class="dark bg-card text-card-foreground max-w-[calc(100vw-2rem)] rounded-2xl p-6 ring-1 ring-foreground/10 sm:max-w-md">
      <form @submit.prevent="submit">
        <DialogHeader>
          <DialogTitle>Open Preset</DialogTitle>
          <DialogDescription>Paste a preset code to load a saved configuration.</DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <Input
            v-model="input"
            placeholder="e.g. --preset AQABAgAAAA"
            autocapitalize="none"
            autocorrect="off"
            spellcheck="false"
            class="h-10 md:h-9"
            @keydown.enter.prevent="submit"
          />
        </div>
        <DialogFooter class="gap-2">
          <DialogClose as-child>
            <button
              type="button"
              class="inline-flex h-9 items-center justify-center rounded-lg px-3 text-sm font-medium ring-1 ring-foreground/10 transition-colors outline-none select-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-foreground/50"
            >
              Cancel
            </button>
          </DialogClose>
          <button
            type="submit"
            :disabled="!input.trim()"
            class="bg-primary text-primary-foreground inline-flex h-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition-colors outline-none select-none hover:opacity-90 focus-visible:ring-2 focus-visible:ring-foreground/50 disabled:pointer-events-none disabled:opacity-50"
          >
            Open
          </button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
