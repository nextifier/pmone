<template>
  <DialogResponsive v-model:open="isOpen" :overflow-content="true">
    <template v-if="$slots.trigger" #trigger="{ open }">
      <slot name="trigger" :open="open" />
    </template>
    <template #default>
      <div class="space-y-6 px-4 pb-10 md:px-6 md:py-5">
        <h3 class="text-foreground text-lg font-semibold tracking-tighter">Share form</h3>

        <!-- Links -->
        <div class="space-y-3">
          <div class="space-y-2">
            <Label>Public link</Label>
            <div class="flex items-center gap-x-2">
              <Input :model-value="publicFormUrl" readonly class="flex-1" />
              <ButtonCopy :text="publicFormUrl" label="Copy link" />
            </div>
          </div>
          <div v-if="form.short_link?.url" class="space-y-2">
            <Label>Short link</Label>
            <div class="flex items-center gap-x-2">
              <Input :model-value="form.short_link.url" readonly class="flex-1" />
              <ButtonCopy :text="form.short_link.url" label="Copy short link" />
            </div>
          </div>
        </div>

        <!-- QR code -->
        <div class="space-y-3">
          <Label>QR code</Label>
          <div class="flex justify-center">
            <ClientOnly>
              <QRCodeComponent v-if="isOpen" :url="shareUrl" class="w-full max-w-[220px]" />
            </ClientOnly>
          </div>
          <div class="flex justify-center gap-2">
            <Button size="sm" @click="downloadQrPng">
              <Icon name="hugeicons:download-01" class="size-4" />
              <span>PNG</span>
            </Button>
            <Button size="sm" variant="outline" @click="downloadQrSvg">
              <Icon name="hugeicons:download-01" class="size-4" />
              <span>SVG</span>
            </Button>
          </div>
        </div>

        <!-- Embed -->
        <div class="space-y-2">
          <Label>Embed</Label>
          <Textarea :model-value="embedSnippet" readonly :rows="3" class="font-mono text-xs" />
          <Button size="sm" variant="outline" @click="copyEmbedSnippet">
            <Icon name="lucide:code" class="size-4" />
            <span>Copy embed code</span>
          </Button>
          <p class="text-muted-foreground text-xs sm:text-sm">
            Paste this snippet into any website to embed the form.
          </p>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import QRCodeComponent from "@/components/QRCode.vue";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useQRCode } from "@/composables/useQRCode";
import { useVModel } from "@vueuse/core";
import { toast } from "vue-sonner";

const props = defineProps({
  open: { type: Boolean, default: undefined },
  form: { type: Object, required: true },
});

const emit = defineEmits(["update:open"]);

const isOpen = useVModel(props, "open", emit, { passive: true });

const config = useRuntimeConfig();
const publicFormUrl = computed(() => `${config.public.siteUrl}/f/${props.form.slug}`);
const shareUrl = computed(() => props.form.short_link?.url || publicFormUrl.value);

const embedSnippet = computed(
  () =>
    `<iframe src="${publicFormUrl.value}?embed=1" width="100%" height="600" style="border:0;" loading="lazy"></iframe>`
);

const { downloadJPG: qrDownloadJPG, downloadSVG: qrDownloadSVG } = useQRCode();

const downloadQrPng = async () => {
  try {
    await qrDownloadJPG(shareUrl.value, `QR-${props.form.slug}.png`);
    toast.success("QR code downloaded");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error generating QR code:", err);
  }
};

const downloadQrSvg = async () => {
  try {
    await qrDownloadSVG(shareUrl.value, `QR-${props.form.slug}.svg`);
    toast.success("QR code downloaded");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error generating SVG:", err);
  }
};

const copyEmbedSnippet = async () => {
  try {
    await navigator.clipboard.writeText(embedSnippet.value);
    toast.success("Embed code copied");
  } catch {
    toast.error("Failed to copy embed code");
  }
};
</script>
