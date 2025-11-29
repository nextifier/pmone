<template>
  <DialogResponsive
    v-model:open="isDialogOpen"
    :isResponsive="false"
    :overflowContent="true"
    :drawerCloseButton="false"
  >
    <template #default="{ data }">
      <div class="container max-w-lg pb-16">
        <div class="grid grid-cols-1 gap-10">
          <ContactForm
            title="Let's Talk"
            description=""
            messageLabel="Event brief"
            messagePlaceholder="Tell us about the event you want.."
            buttonLabel="Send my outing inquiry"
          />

          <div class="flex flex-col items-start">
            <h6
              class="text-primary text-lg font-semibold tracking-tighter sm:text-xl"
            >
              Prefer to Connect Directly?
            </h6>

            <div class="mt-3 flex flex-wrap gap-2">
              <NuxtLink
                v-for="(link, index) in links"
                :key="index"
                :to="link.to"
                :target="link.openInNewTab ? '_blank' : ''"
                class="text-primary bg-muted hover:bg-border flex items-center justify-center gap-x-1 rounded-lg px-4 py-2 text-sm font-semibold tracking-tighter"
              >
                <Icon
                  v-if="link.iconName"
                  :name="link.iconName"
                  class="size-5 shrink-0"
                />
                <span>{{ link.label }}</span>
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
const uiStore = useUiStore();
const isDialogOpen = computed({
  get() {
    return uiStore.isInquiryDialogOpen;
  },
  set(value) {
    if (value) {
      uiStore.openInquiryDialog();
    } else {
      uiStore.closeInquiryDialog();
    }
  },
});

const links = [
  {
    label: "WhatsApp",
    to: `https://api.whatsapp.com/send?phone=${useAppConfig().contact.whatsapp}&text=Hai, ${useAppConfig().app.shortName}`,
    openInNewTab: true,
    iconName: "hugeicons:whatsapp",
  },
  {
    label: "Email",
    to: `mailto:${useAppConfig().contact.email}`,
    iconName: "hugeicons:mail-02",
  },
];
</script>
