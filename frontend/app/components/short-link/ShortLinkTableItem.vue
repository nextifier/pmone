<template>
  <div class="flex flex-col gap-y-0.5 overflow-hidden">
    <div class="flex items-center gap-x-0.5">
      <NuxtLink
        :to="`/links/${link.slug}`"
        class="text-muted-foreground inline text-sm tracking-tight"
      >
        {{ useRuntimeConfig().public.siteUrl.replace(/^https?:\/\//, "") }}/<span
          class="text-foreground font-medium"
          >{{ link.slug }}</span
        >
      </NuxtLink>

      <ButtonCopy :text="shortLinkUrl" />

      <DialogResponsive v-model:open="qrDialogOpen" dialogMaxWidth="360px">
        <template #trigger="{ open }">
          <button
            @click="open()"
            v-tippy="'QR Code'"
            aria-label="QR Code"
            class="text-muted-foreground hover:text-foreground -ml-1 flex size-7 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:qr-code-01" class="size-4 shrink-0" />
          </button>
        </template>
        <template #default>
          <div class="flex flex-col items-center gap-5 px-6 pb-12 md:py-8">
            <div class="text-center">
              <div class="page-title">QR Code</div>
              <div class="mt-0.5 flex items-center gap-x-1">
                <p class="text-muted-foreground text-sm tracking-tight">
                  {{ shortLinkUrl }}
                </p>
                <ButtonCopy :text="shortLinkUrl" />
              </div>
            </div>

            <ClientOnly>
              <QRCode
                v-if="qrDialogOpen"
                :url="shortLinkUrl"
                class="w-full max-w-[240px]"
              />
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

    <div class="text-muted-foreground line-clamp-1 inline-flex items-center gap-x-0.5">
      <Icon name="hugeicons:arrow-move-down-right" class="size-4 shrink-0 -translate-y-1" />
      <span class="text-xs tracking-tight">{{
        link.destination_url.replace(/^https?:\/\//, "")
      }}</span>
    </div>
  </div>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import QRCode from "@/components/QRCode.vue";
import { useQRCode } from "@/composables/useQRCode";
import { toast } from "vue-sonner";

const props = defineProps({
  link: {
    type: Object,
    required: true,
  },
});

const config = useRuntimeConfig();
const shortLinkUrl = computed(() => `${config.public.siteUrl}/${props.link.slug}`);
const qrDialogOpen = ref(false);

const { downloadSVG, downloadJPG } = useQRCode();

const downloadQR = async (format) => {
  try {
    if (format === "svg") {
      await downloadSVG(shortLinkUrl.value, `QR-${props.link.slug}.svg`);
    } else {
      await downloadJPG(shortLinkUrl.value, `QR-${props.link.slug}.png`);
    }
    toast.success("QR code downloaded!");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error downloading QR code:", err);
  }
};
</script>
