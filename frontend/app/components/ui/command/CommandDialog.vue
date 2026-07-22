<script setup lang="ts">
import type { DialogRootEmits, DialogRootProps } from "reka-ui";
import { reactiveOmit } from "@vueuse/core";
import { Dialog, DialogContent, DialogTitle, DialogDescription } from "@/components/ui/dialog";
import { useForwardPropsEmits, VisuallyHidden } from "reka-ui";
import Command from "./Command.vue";

const props = withDefaults(
  defineProps<
    DialogRootProps & {
      title?: string;
      description?: string;
      showCloseButton?: boolean;
    }
  >(),
  {
    title: "Command Menu",
    description: "Search and navigate",
    showCloseButton: false,
  }
);
const emits = defineEmits<DialogRootEmits>();

const delegatedProps = reactiveOmit(props, "title", "description", "showCloseButton");
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <Dialog v-bind="forwarded">
    <DialogContent
      class="cn-command-dialog top-1/3 translate-y-0 overflow-hidden p-0"
      :show-close-button="showCloseButton"
    >
      <VisuallyHidden>
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>{{ description }}</DialogDescription>
      </VisuallyHidden>
      <Command>
        <slot />
      </Command>
    </DialogContent>
  </Dialog>
</template>
