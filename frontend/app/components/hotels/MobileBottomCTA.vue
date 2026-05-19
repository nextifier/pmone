<script setup>
import { Button } from "@/components/ui/button";

defineProps({
  total: { type: Number, default: 0 },
  ctaLabel: { type: String, default: "Continue" },
  ctaDisabled: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
});

const emit = defineEmits(["primary", "showBreakdown"]);

const fmtRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>

<template>
  <div
    class="bg-background/95 supports-[backdrop-filter]:bg-background/85 fixed inset-x-0 bottom-0 z-40 border-t px-4 py-3 backdrop-blur lg:hidden"
  >
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
      <button
        type="button"
        class="text-left"
        :disabled="total === 0"
        @click="emit('showBreakdown')"
      >
        <div class="text-muted-foreground text-xs tracking-tight">Total</div>
        <div class="flex items-center gap-1 text-base font-semibold tabular-nums tracking-tight">
          <span>Rp{{ fmtRupiah(total) }}</span>
          <Icon
            v-if="total > 0"
            name="hugeicons:arrow-up-01"
            class="text-muted-foreground size-3.5"
          />
        </div>
      </button>
      <Button
        :disabled="ctaDisabled || submitting"
        class="flex-1 max-w-[60%]"
        @click="emit('primary')"
      >
        <Icon v-if="submitting" name="svg-spinners:180-ring" class="size-4" />
        <span>{{ ctaLabel }}</span>
      </Button>
    </div>
  </div>
</template>
