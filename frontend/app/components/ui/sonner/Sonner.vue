<script lang="ts" setup>
import { Toaster as Sonner, type ToasterProps } from "vue-sonner";

const props = defineProps<ToasterProps>();
</script>

<template>
  <Sonner
    class="toaster group tracking-tight"
    v-bind="props"
    :style="{
      '--normal-bg': 'var(--popover)',
      '--normal-text': 'var(--popover-foreground)',
      '--normal-border': 'var(--border)',
    }"
  >
    <template #info-icon>
      <slot name="info-icon">
        <Icon name="lucide:info" />
      </slot>
    </template>

    <template #success-icon>
      <slot name="success-icon">
        <Icon name="lucide:circle-check-big" />
      </slot>
    </template>

    <template #warning-icon>
      <slot name="warning-icon">
        <Icon name="lucide:triangle-alert" />
      </slot>
    </template>

    <template #error-icon>
      <slot name="error-icon">
        <Icon name="lucide:octagon-x" />
      </slot>
    </template>

    <template #loading-icon>
      <Spinner class="text-background size-4 shrink-0" />
    </template>
  </Sonner>
</template>
