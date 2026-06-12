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

      <FormShareDialog :form="form">
        <template #trigger="{ open }">
          <button
            @click="open()"
            v-tippy="'Share & QR code'"
            aria-label="Share form"
            class="text-muted-foreground hover:text-foreground -ml-1 flex size-7 shrink-0 items-center justify-center rounded-lg"
          >
            <Icon name="hugeicons:qr-code-01" class="size-4 shrink-0" />
          </button>
        </template>
      </FormShareDialog>
    </div>
  </div>
</template>

<script setup>
import FormShareDialog from "@/components/form-builder/FormShareDialog.vue";

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
});

const config = useRuntimeConfig();
const publicUrl = computed(() => `${config.public.siteUrl}/f/${props.form.slug}`);
</script>
