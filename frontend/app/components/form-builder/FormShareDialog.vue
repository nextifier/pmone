<template>
  <ResponsiveDialog v-model:open="isOpen" :overflow-content="true">
    <template v-if="$slots.trigger" #trigger="{ open }">
      <slot name="trigger" :open="open" />
    </template>
    <template #default>
      <div class="space-y-6 px-4 pt-5 pb-8 md:px-6 md:py-5">
        <h3 class="text-foreground text-lg font-semibold tracking-tighter">Share form</h3>

        <!-- Links -->
        <div class="space-y-3">
          <div class="space-y-2">
            <Label>{{ isEvent ? "Event website link" : "Public link" }}</Label>
            <div class="flex items-center gap-x-2">
              <Input :model-value="formUrl" readonly class="flex-1" />
              <ButtonCopy :text="formUrl" label="Copy link" />
            </div>
          </div>
          <div v-if="!isEvent && form.short_link?.url" class="space-y-2">
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

        <!-- Embed. Only offered for the pmone.id copy: the event website already
             renders the form as a page of its own, so re-embedding it there
             would nest the site inside itself. -->
        <div v-if="!isEvent" class="space-y-2">
          <Label>Embed</Label>
          <Textarea :model-value="embedSnippet" readonly :rows="3" class="font-mono text-xs" />
          <Button size="sm" variant="outline" @click="copyEmbedSnippet">
            <Icon name="hugeicons:source-code" class="size-4" />
            <span>Copy embed code</span>
          </Button>
          <p class="text-muted-foreground text-xs sm:text-sm">
            Paste this snippet into any website to embed the form.
          </p>
        </div>
      </div>
    </template>
  </ResponsiveDialog>
</template>

<script setup>
import { QRCode as QRCodeComponent, useQRCode } from "@/components/ui/qr-code";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { useVModel } from "@vueuse/core";
import { toast } from "vue-sonner";

const props = defineProps({
  open: { type: Boolean, default: undefined },
  form: { type: Object, required: true },
  /**
   * Which copy of the form this dialog is about. A form that belongs to a
   * project is served from two places, and each deserves its own link and its
   * own QR code rather than one dialog listing both.
   */
  target: {
    type: String,
    default: "pmone",
    validator: (value) => ["pmone", "event"].includes(value),
  },
});

const emit = defineEmits(["update:open"]);

const isOpen = useVModel(props, "open", emit, { passive: true });

const { pmoneUrl, eventUrl } = useFormPublicUrls(() => props.form);

const isEvent = computed(() => props.target === "event");

const formUrl = computed(() => (isEvent.value ? eventUrl.value : pmoneUrl.value) ?? "");

// The short link resolves to the pmone.id copy, so it only stands in for the QR
// on that side; the event copy encodes its own URL directly.
const shareUrl = computed(() =>
  isEvent.value ? formUrl.value : props.form.short_link?.url || pmoneUrl.value
);

const embedSnippet = computed(
  () =>
    `<iframe src="${pmoneUrl.value}?embed=1" width="100%" height="600" style="border:0;" loading="lazy"></iframe>`
);

const { downloadJPG: qrDownloadJPG, downloadSVG: qrDownloadSVG } = useQRCode();

// Both copies can be downloaded in one sitting, so the filenames have to differ
// or the second download lands next to the first as "QR-slug (1).png".
const qrFileName = computed(() => `QR-${props.form.slug}${isEvent.value ? "-event" : ""}`);

const downloadQrPng = async () => {
  try {
    await qrDownloadJPG(shareUrl.value, `${qrFileName.value}.png`);
    toast.success("QR code downloaded");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error generating QR code:", err);
  }
};

const downloadQrSvg = async () => {
  try {
    await qrDownloadSVG(shareUrl.value, `${qrFileName.value}.svg`);
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
