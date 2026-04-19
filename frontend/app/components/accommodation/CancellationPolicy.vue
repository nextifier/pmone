<template>
  <div class="rounded-md border bg-muted/30 p-3 sm:p-4 space-y-2 text-sm tracking-tight">
    <div class="flex items-center gap-2">
      <Icon name="lucide:info" class="size-4 text-muted-foreground shrink-0" />
      <h3 class="font-semibold tracking-tight">Cancellation Policy</h3>
    </div>
    <ul class="text-muted-foreground space-y-1 text-xs sm:text-sm tracking-tight list-disc list-inside">
      <li>Cancel 14+ days before check-in — <span class="text-foreground font-medium">100% refund</span></li>
      <li>Cancel 7-13 days before check-in — <span class="text-foreground font-medium">50% refund</span></li>
      <li>Cancel less than 7 days before check-in — <span class="text-destructive font-medium">no refund</span></li>
    </ul>
    <p v-if="customPolicy" class="text-muted-foreground text-xs tracking-tight pt-2 border-t whitespace-pre-line">
      {{ customPolicy }}
    </p>
  </div>
</template>

<script setup>
defineProps({
  customPolicy: { type: String, default: '' },
})
</script>
