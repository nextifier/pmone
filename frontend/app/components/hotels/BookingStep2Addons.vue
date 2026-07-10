<script setup>
import { Label } from "../ui/label";
import { RadioGroup, RadioGroupItem } from "../ui/radio-group";
import { Textarea } from "../ui/textarea";

const props = defineProps({
  options: { type: Array, default: () => [] },
  selected: { type: Object, default: () => ({}) },
  notes: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["toggle", "update:notes"]);

const fmtRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);

function meta(opt) {
  const parts = [];
  if (opt.vehicle_type) parts.push(opt.vehicle_type);
  if (opt.max_pax) parts.push(`max ${opt.max_pax} pax`);
  return parts.join(" · ");
}

function iconFor(opt) {
  const vt = (opt?.vehicle_type || "").toLowerCase();
  if (vt.includes("bus") || vt.includes("shuttle") || vt.includes("coach")) {
    return "hugeicons:bus-01";
  }
  return "hugeicons:car-01";
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-base font-medium tracking-tight">Add-ons (Optional)</h2>
      <p class="text-muted-foreground mt-1 text-sm tracking-tight">
        Pick additional services like airport transfers. You can skip this step.
      </p>
    </div>

    <div
      v-if="!options.length"
      class="text-muted-foreground rounded-2xl border border-dashed py-10 text-center text-sm tracking-tight"
    >
      No add-on services available for this hotel.
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="opt in options"
        :key="opt.id"
        class="bg-background space-y-3 rounded-2xl border p-4 transition-colors sm:p-5"
        :class="selected[opt.id] && 'border-primary/40 bg-primary/5'"
      >
        <button
          type="button"
          class="flex w-full items-start gap-3 text-left"
          :aria-pressed="!!selected[opt.id]"
          @click="emit('toggle', { id: opt.id, on: !selected[opt.id] })"
        >
          <div class="bg-muted flex size-10 shrink-0 items-center justify-center rounded-full">
            <Icon :name="iconFor(opt)" class="text-foreground/70 size-5" />
          </div>
          <div class="min-w-0 flex-1 space-y-0.5">
            <p class="text-sm font-medium tracking-tight">{{ opt.label }}</p>
            <p v-if="meta(opt)" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              {{ meta(opt) }}
            </p>
            <p class="text-foreground pt-1 text-sm font-medium tabular-nums tracking-tight">
              Rp{{ fmtRupiah(opt.price) }}
            </p>
          </div>
          <RadioGroup
            :model-value="selected[opt.id] ? String(opt.id) : ''"
            class="pointer-events-none grid-flow-col"
            aria-hidden="true"
          >
            <RadioGroupItem
              :value="String(opt.id)"
              tabindex="-1"
              class="mt-1 size-5"
            />
          </RadioGroup>
        </button>

        <div v-if="selected[opt.id]" class="space-y-1.5 border-t pt-3">
          <Label class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            Notes for this transfer (optional)
          </Label>
          <Textarea
            :model-value="notes[opt.id] ?? ''"
            rows="2"
            placeholder="e.g. flight number, pickup time, contact name"
            maxlength="1000"
            @update:model-value="(v) => emit('update:notes', { id: opt.id, notes: v ?? '' })"
          />
        </div>
      </div>
    </div>
  </div>
</template>
