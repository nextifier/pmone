<script setup>
import IllustrationFrame from "./IllustrationFrame.vue";
</script>

<template>
  <IllustrationFrame max-width="50" overflow>
    <div class="flex flex-1 items-stretch justify-end p-2">
      <div class="border-border bg-card flex w-3/5 flex-col rounded-l-xl border">
        <div class="border-border border-b p-3">
          <div class="bg-muted-foreground/40 h-1.5 w-3/5 rounded-full" />
        </div>
        <div class="flex-1 space-y-1.5 p-3">
          <div class="bg-muted-foreground/8 h-2 rounded-sm" />
          <div class="bg-muted-foreground/8 h-2 rounded-sm" />
          <div class="bg-muted-foreground/8 h-2 w-4/5 rounded-sm" />
        </div>
        <div class="border-border border-t p-3">
          <div class="h-3 w-12 rounded-sm bg-linear-to-b from-(--btn-from) to-(--btn-to)" />
        </div>
      </div>
    </div>
  </IllustrationFrame>
</template>
