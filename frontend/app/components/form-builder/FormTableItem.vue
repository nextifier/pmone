<template>
  <div class="flex flex-col gap-y-0.5 overflow-hidden">
    <NuxtLink
      :to="`/forms/${form.slug}`"
      class="text-sm font-medium tracking-tight hover:underline"
    >
      {{ form.title }}
    </NuxtLink>

    <div class="flex items-center gap-x-0.5 overflow-hidden">
      <a
        :href="publicUrl"
        target="_blank"
        rel="noopener noreferrer"
        v-tippy="'Open public form'"
        class="text-muted-foreground min-w-0 truncate text-xs tracking-tight hover:underline"
      >
        {{ publicUrl.replace(/^https?:\/\//, "") }}
      </a>

      <ButtonCopy :text="publicUrl" />

      <DialogResponsive v-model:open="qrDialogOpen" dialogMaxWidth="360px">
        <template #trigger="{ open }">
          <button
            @click="open()"
            v-tippy="'QR Code'"
            aria-label="QR Code"
            class="text-muted-foreground hover:text-foreground -ml-1 flex size-7 shrink-0 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:qr-code-01" class="size-4 shrink-0" />
          </button>
        </template>
        <template #default>
          <div class="flex flex-col items-center gap-5 px-6 pb-12 md:py-8">
            <div class="text-center">
              <div class="page-title">QR Code</div>
              <div class="mt-0.5 flex items-center gap-x-1">
                <p class="text-muted-foreground text-sm tracking-tight">{{ publicUrl }}</p>
                <ButtonCopy :text="publicUrl" />
              </div>
            </div>

            <ClientOnly>
              <QRCode v-if="qrDialogOpen" :url="publicUrl" class="w-full max-w-60" />
            </ClientOnly>

            <div class="flex gap-2">
              <button
                @click="downloadQR('jpg')"
                class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              >
                <Icon name="hugeicons:jpg-01" class="size-4 shrink-0" />
                Download JPG
              </button>
              <button
                @click="downloadQR('svg')"
                class="bg-muted text-foreground hover:bg-border flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
              >
                <Icon name="hugeicons:svg-01" class="size-4 shrink-0" />
                Download SVG
              </button>
            </div>
          </div>
        </template>
      </DialogResponsive>
    </div>
  </div>
</template>

<script setup>
import QRCode from "@/components/QRCode.vue";
import { useQRCode } from "@/composables/useQRCode";
import { toast } from "vue-sonner";

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
});

const config = useRuntimeConfig();
const publicUrl = computed(() => `${config.public.siteUrl}/f/${props.form.slug}`);
const qrDialogOpen = ref(false);

const { downloadSVG, downloadJPG } = useQRCode();

const downloadQR = async (format) => {
  try {
    if (format === "svg") {
      await downloadSVG(publicUrl.value, `QR-${props.form.slug}.svg`);
    } else {
      await downloadJPG(publicUrl.value, `QR-${props.form.slug}.png`);
    }
    toast.success("QR code downloaded!");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error downloading QR code:", err);
  }
};
</script>
