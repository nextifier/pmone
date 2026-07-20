<script setup lang="ts">
import { cn } from "@/lib/utils";
import { X } from "lucide-vue-next";
import {
  DialogClose,
  DialogContent,
  type DialogContentEmits,
  type DialogContentProps,
  DialogPortal,
  useForwardPropsEmits,
} from "reka-ui";
import { computed, type HTMLAttributes } from "vue";
import DialogOverlay from "./DialogOverlay.vue";

const props = withDefaults(
  defineProps<
    DialogContentProps & { class?: HTMLAttributes["class"]; showCloseButton?: boolean }
  >(),
  { showCloseButton: true },
);
const emits = defineEmits<DialogContentEmits>();

const delegatedProps = computed(() => {
  const { class: _, showCloseButton: __, ...delegated } = props;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <DialogPortal>
    <DialogOverlay />
    <DialogContent
      data-slot="dialog-content"
      v-bind="forwarded"
      :class="
        cn(
          'cn-dialog-content fixed top-1/2 left-1/2 z-50 max-h-[calc(100%-4rem)] w-full -translate-x-1/2 -translate-y-1/2 overflow-y-auto outline-none',
          props.class,
        )
      "
    >
      <slot />

      <DialogClose
        v-if="showCloseButton"
        data-slot="dialog-close"
        class="ring-offset-background focus:ring-ring data-[state=open]:bg-muted data-[state=open]:text-muted-foreground hover:bg-muted group absolute top-2 right-2 flex size-8 cursor-pointer items-center justify-center rounded-full transition-colors disabled:pointer-events-none [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
      >
        <X class="opacity-70 transition-opacity group-hover:opacity-100" />
        <span class="sr-only">Close</span>
      </DialogClose>
    </DialogContent>
  </DialogPortal>
</template>
