<template>
  <div class="grid grid-cols-1 gap-y-5">
    <button
      type="button"
      @click="
        copy(url);
        closeDialog();
        toast.success('Copied to clipboard.');
      "
      :aria-label="copied ? 'Copied' : 'Copy to clipboard'"
      :disabled="copied"
      v-ripple
      class="border-border bg-muted text-muted-foreground flex w-full max-w-md justify-between rounded-lg border disabled:opacity-100"
    >
      <div
        class="scroll-fade-x no-scrollbar flex shrink items-center justify-start overflow-hidden px-3 py-1 text-left text-sm tracking-tight"
        v-tippy="url"
      >
        <span class="text-nowrap">{{ url }}</span>
      </div>
      <div
        class="bg-primary hover:bg-primary/80 text-primary-foreground flex w-[6.5rem] shrink-0 items-center justify-start gap-x-1 rounded-md px-2.5 py-2.5 text-sm font-medium tracking-tight transition"
      >
        <div class="relative flex size-4 shrink-0 items-center justify-center">
          <div
            :class="[
              'transition-all',
              copied ? 'scale-100 opacity-100' : 'scale-0 opacity-0',
            ]"
          >
            <Icon name="lucide:check" class="size-4 shrink-0 text-green-600" />
          </div>
          <div
            :class="[
              'absolute transition-all',
              copied ? 'scale-0 opacity-0' : 'scale-100 opacity-100',
            ]"
          >
            <Icon name="lucide:copy" class="size-4 shrink-0" />
          </div>
        </div>

        <span class="text-nowrap">{{ copied ? "Copied!" : "Copy Link" }}</span>
      </div>
    </button>
    <div class="flex justify-center gap-x-3">
      <ClientOnly>
        <ShareNetwork
          v-for="social in socialNetworks"
          :key="social.slug"
          :network="social.slug"
          :url="url"
          :title="title"
          @click="closeDialog"
          class="hover:bg-opacity-80 flex aspect-square size-12 cursor-pointer items-center justify-center rounded-full border border-white/15 text-white transition active:scale-98"
          :style="`background: ${social.color}`"
          v-ripple
          v-tippy="`Share to ${social.name}`"
        >
          <Icon :name="social.iconName" class="size-6" />
        </ShareNetwork>
      </ClientOnly>
    </div>
  </div>
</template>

<script setup>
import { useClipboard } from "@vueuse/core";
import { toast } from "vue-sonner";

const { copy, copied } = useClipboard();

defineProps({
  title: {
    type: String,
    required: true,
  },
  url: {
    type: String,
    required: true,
  },
});

const socialNetworks = [
  {
    name: "Facebook",
    slug: "facebook",
    color: "#1877F2",
    iconName: "ri:facebook-fill",
  },
  {
    name: "WhatsApp",
    slug: "whatsapp",
    color: "#1ACC3E",
    iconName: "ri:whatsapp-line",
  },
  {
    name: "X",
    slug: "x",
    color: "#000000",
    iconName: "ri:twitter-x-fill",
  },
];

const dialogControls = inject("dialogControls", null);

const closeDialog = () => {
  dialogControls?.close();
};
</script>
