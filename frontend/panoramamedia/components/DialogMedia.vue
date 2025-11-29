<script setup lang="ts">
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
  DialogTrigger,
} from "reka-ui";
</script>

<template>
  <DialogRoot>
    <DialogTrigger as-child>
      <slot name="trigger" />
    </DialogTrigger>
    <DialogPortal>
      <DialogOverlay
        class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 fixed inset-0 z-50 bg-black/80 backdrop-blur-md"
      />
      <DialogContent
        class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 3xl:max-w-(--breakpoint-xl) fixed top-1/2 left-1/2 z-50 w-full max-w-(--breakpoint-lg) -translate-x-1/2 -translate-y-1/2 px-2 duration-200"
      >
        <DialogTitle>
          <slot name="title" />
        </DialogTitle>
        <DialogDescription>
          <slot name="description" />
        </DialogDescription>

        <slot />

        <DialogClose
          class="hover:bg-opacity-80 absolute -bottom-4 left-1/2 flex size-14 -translate-x-1/2 translate-y-full items-center justify-center rounded-full bg-white text-black transition lg:-top-3 lg:right-2 lg:bottom-auto lg:left-auto lg:size-16 lg:translate-x-0 lg:-translate-y-full"
          aria-label="Close"
        >
          <IconClose class="size-4 lg:size-5" />
        </DialogClose>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
