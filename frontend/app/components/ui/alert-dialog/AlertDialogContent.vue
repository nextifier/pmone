<script setup lang="ts">
import type { AlertDialogContentEmits, AlertDialogContentProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import {
  AlertDialogContent,

  AlertDialogOverlay,
  AlertDialogPortal,
  useForwardPropsEmits,
} from "reka-ui"
import { cn } from "@/lib/utils"

const props = withDefaults(
  defineProps<
    AlertDialogContentProps & { class?: HTMLAttributes["class"]; size?: "default" | "sm" }
  >(),
  { size: "default" },
)
const emits = defineEmits<AlertDialogContentEmits>()

const delegatedProps = reactiveOmit(props, "class", "size")

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <AlertDialogPortal>
    <AlertDialogOverlay
      data-slot="alert-dialog-overlay"
      class="cn-alert-dialog-overlay ease-(--modal-ease) data-open:duration-(--modal-open-dur) data-closed:duration-(--modal-close-dur) motion-reduce:animate-none! fixed inset-0 z-50"
    />
    <AlertDialogContent
      data-slot="alert-dialog-content"
      :data-size="size"
      v-bind="forwarded"
      :class="
        cn(
          'cn-alert-dialog-content group/alert-dialog-content fixed top-1/2 left-1/2 z-50 grid max-h-[calc(100%-4rem)] w-full -translate-x-1/2 -translate-y-1/2 overflow-y-auto outline-none',
          'ease-(--modal-ease) data-open:duration-(--modal-open-dur) data-open:zoom-in-96 data-closed:duration-(--modal-close-dur) data-closed:zoom-out-96 motion-reduce:animate-none!',
          props.class,
        )
      "
    >
      <slot />
    </AlertDialogContent>
  </AlertDialogPortal>
</template>
