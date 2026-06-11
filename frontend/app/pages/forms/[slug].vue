<template>
  <div class="flex flex-col pb-16 sm:container">
    <template v-if="initialLoading">
      <div class="flex items-center justify-center py-20">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-base tracking-tight">Loading</span>
        </div>
      </div>
    </template>

    <template v-else-if="error">
      <div class="flex items-center justify-center py-20">
        <div class="flex flex-col items-center gap-y-4 text-center">
          <div class="space-y-1">
            <h3 class="text-lg font-semibold tracking-tighter">{{ error }}</h3>
          </div>
          <NuxtLink
            to="/forms"
            class="bg-primary text-primary-foreground hover:bg-primary/80 flex items-center gap-x-1.5 rounded-lg px-4 py-2 text-sm font-medium tracking-tight active:scale-98"
          >
            <Icon name="lucide:arrow-left" class="size-4 shrink-0" />
            <span>Back to Forms</span>
          </NuxtLink>
        </div>
      </div>
    </template>

    <template v-else-if="form">
      <!-- Header info -->
      <div class="flex flex-col items-start gap-y-4 px-4 pt-4 sm:px-0">
        <ButtonBack destination="/forms" force-destination />
        <div class="flex w-full flex-wrap items-center gap-x-3 gap-y-2">
          <h1 class="text-lg font-semibold tracking-tight">{{ form.title }}</h1>
          <Badge :variant="statusBadge(form.status).variant" :icon="statusBadge(form.status).icon">
            {{ form.status.charAt(0).toUpperCase() + form.status.slice(1) }}
          </Badge>
          <span v-if="form.responses_count !== undefined" class="text-muted-foreground text-sm tracking-tight">
            {{ form.responses_count }} {{ form.responses_count === 1 ? "response" : "responses" }}
          </span>
          <div v-if="form.status === 'published'" class="ml-auto flex items-center gap-1">
            <ButtonCopy :text="publicFormUrl" />
            <button
              type="button"
              v-tippy="'Share form'"
              class="text-muted-foreground hover:text-foreground flex size-7 items-center justify-center rounded-lg"
              @click="shareDialogOpen = true"
            >
              <Icon name="hugeicons:share-03" class="size-4 shrink-0" />
            </button>
            <a
              :href="`/f/${form.slug}`"
              target="_blank"
              rel="noopener noreferrer"
              v-tippy="'Open public form'"
              class="text-muted-foreground hover:text-foreground flex size-7 items-center justify-center rounded-lg"
            >
              <Icon name="hugeicons:arrow-up-right-01" class="size-4 shrink-0" />
            </a>
          </div>
        </div>
      </div>

      <!-- Share dialog -->
      <DialogResponsive v-model:open="shareDialogOpen" :overflow-content="true">
        <template #default>
          <div class="space-y-6 px-4 pb-10 md:px-6 md:py-5">
            <h3 class="text-primary text-lg font-semibold tracking-tight">Share Form</h3>

            <!-- Links -->
            <div class="space-y-3">
              <div class="space-y-2">
                <Label>Public Link</Label>
                <div class="flex items-center gap-x-2">
                  <Input :model-value="publicFormUrl" readonly class="flex-1" />
                  <ButtonCopy :text="publicFormUrl" label="Copy link" />
                </div>
              </div>
              <div v-if="form.short_link?.url" class="space-y-2">
                <Label>Short Link</Label>
                <div class="flex items-center gap-x-2">
                  <Input :model-value="form.short_link.url" readonly class="flex-1" />
                  <ButtonCopy :text="form.short_link.url" label="Copy short link" />
                </div>
              </div>
            </div>

            <!-- QR code -->
            <div class="space-y-3">
              <Label>QR Code</Label>
              <div class="flex justify-center">
                <ClientOnly>
                  <QRCodeComponent :url="shareUrl" class="w-full max-w-[220px]" />
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
              <p class="text-muted-foreground text-xs">
                Paste this snippet into any website to embed the form.
              </p>
            </div>
          </div>
        </template>
      </DialogResponsive>

      <TabNav :tabs="formTabs" class="mt-4" />

      <div ref="contentArea" class="pt-6">
        <NuxtPage :form="form" @refresh="refreshForm" />
      </div>
    </template>
  </div>
</template>

<script setup>
import QRCodeComponent from "@/components/QRCode.vue";
import { TabNav } from "@/components/ui/tab-nav";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

definePageMeta({
  layout: "app",
  middleware: ["sanctum:auth", "permission"],
  permissions: ["forms.read"],
});

const route = useRoute();

const {
  data: formResponse,
  pending: initialLoading,
  error: formError,
  refresh: refreshForm,
} = await useLazySanctumFetch(() => `/api/forms/${route.params.slug}`, {
  key: `form-detail-${route.params.slug}`,
});

const form = computed(() => formResponse.value?.data || null);

const error = computed(() => {
  if (!formError.value) return null;
  const err = formError.value;
  if (err.statusCode === 404) return "Form not found";
  if (err.statusCode === 403) return "You do not have permission to view this form";
  return err.message || "Failed to load form";
});

usePageMeta(null, {
  title: computed(() => form.value?.title || "Form"),
});

const statusBadge = (status) => {
  switch (status) {
    case "published":
      return { variant: "success", icon: "hugeicons:checkmark-circle-02" };
    case "draft":
      return { variant: "info", icon: "hugeicons:information-circle" };
    case "closed":
      return { variant: "destructive", icon: "hugeicons:cancel-circle" };
    default:
      return { variant: "outline", icon: "hugeicons:information-circle" };
  }
};

const contentArea = ref(null);

const formBase = computed(() => `/forms/${route.params.slug}`);

const formTabs = computed(() => [
  { label: "Settings", to: formBase.value, exact: true },
  { label: "Fields", to: `${formBase.value}/fields` },
  { label: "Responses", to: `${formBase.value}/responses` },
  { label: "Analytics", to: `${formBase.value}/analytics` },
]);

const publicFormUrl = computed(() => {
  if (!form.value) return "";
  const frontendUrl = window?.location?.origin || "https://pmone.id";
  return `${frontendUrl}/f/${form.value.slug}`;
});

// Share dialog
const shareDialogOpen = ref(false);
const { downloadJPG: qrDownloadJPG, downloadSVG: qrDownloadSVG } = useQRCode();

const shareUrl = computed(() => form.value?.short_link?.url || publicFormUrl.value);

const embedSnippet = computed(
  () =>
    `<iframe src="${publicFormUrl.value}?embed=1" width="100%" height="600" style="border:0;" loading="lazy"></iframe>`
);

const downloadQrPng = async () => {
  try {
    await qrDownloadJPG(shareUrl.value, `QR-${form.value.slug}.png`);
    toast.success("QR code downloaded");
  } catch (err) {
    toast.error("Failed to download QR code");
    console.error("Error generating QR code:", err);
  }
};

const downloadQrSvg = async () => {
  try {
    await qrDownloadSVG(shareUrl.value, `QR-${form.value.slug}.svg`);
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

useTabSwipe(contentArea, formTabs);

provide("form", form);
provide("refreshForm", refreshForm);
</script>
