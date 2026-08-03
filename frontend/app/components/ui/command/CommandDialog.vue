<script setup lang="ts">
import type { DialogRootEmits, DialogRootProps } from "reka-ui";
import { reactiveOmit } from "@vueuse/core";
import { Dialog, DialogContent, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { cn } from "@/lib/utils";
import { useForwardPropsEmits, VisuallyHidden } from "reka-ui";
import type { HTMLAttributes } from "vue";

const props = withDefaults(
  defineProps<
    DialogRootProps & {
      title?: string;
      description?: string;
      class?: HTMLAttributes["class"];
      showCloseButton?: boolean;
    }
  >(),
  {
    title: "Command Palette",
    description: "Search for a command to run...",
    showCloseButton: false,
  }
);
const emits = defineEmits<DialogRootEmits>();

const delegatedProps = reactiveOmit(props, "title", "description", "class", "showCloseButton");
const forwarded = useForwardPropsEmits(delegatedProps, emits);

// Reka's Combobox mounts its own DismissableLayer inside the dialog, and only the
// topmost layer reacts to Escape. That layer wins, then tries to close a combobox
// whose `open` is pinned to `true` by `Command` — so the dialog never sees the key.
// A plain DOM listener sidesteps the layer stack. It cannot go on `<DialogContent>`:
// that component's root is a portal, so fallthrough listeners never reach an element.
function handleEscape(event: KeyboardEvent) {
  event.preventDefault();
  emits("update:open", false);
}
</script>

<template>
  <Dialog v-bind="forwarded">
    <DialogContent
      :class="cn('cn-command-dialog top-1/3 translate-y-0 overflow-hidden p-0', props.class)"
      :show-close-button="showCloseButton"
    >
      <VisuallyHidden>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </VisuallyHidden>
      <div class="contents" @keydown.escape="handleEscape">
        <slot />
      </div>
    </DialogContent>
  </Dialog>
</template>
