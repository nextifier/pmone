<template>
  <div :class="containerClass">
    <ClientOnly>
      <QRCode v-if="token" :url="token" :size="size" />
      <template #fallback>
        <div
          role="img"
          aria-label="Loading QR code"
          class="bg-muted flex aspect-square w-full items-center justify-center rounded-lg"
        >
          <Icon name="hugeicons:qr-code" class="text-muted-foreground size-8" aria-hidden="true" />
        </div>
      </template>
    </ClientOnly>
  </div>
</template>

<script setup>
import QRCode from "@/components/QRCode.vue";

defineProps({
  token: {
    type: String,
    default: "",
  },
  size: {
    type: Number,
    default: 220,
  },
  containerClass: {
    type: String,
    default: "w-full max-w-[180px]",
  },
});
</script>
