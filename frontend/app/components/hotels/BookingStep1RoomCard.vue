<script setup>
import { Button } from "../ui/button";
import { ButtonGroup } from "../ui/button-group";
import { Input } from "../ui/input";
import { Label } from "../ui/label";
import { Lightbox } from "../ui/lightbox";
import { Textarea } from "../ui/textarea";
import { computed } from "vue";

const props = defineProps({
  room: { type: Object, required: true },
  qty: { type: Number, default: 0 },
  notes: { type: String, default: "" },
  available: { type: [Number, null], default: null },
  preview: { type: Object, default: null },
  nights: { type: Number, default: 0 },
});

const emit = defineEmits(["update:qty", "update:notes"]);

const DEFAULT_MAX = 20;

const maxQty = computed(() => {
  if (props.available == null) return DEFAULT_MAX;
  return Math.max(0, Math.min(DEFAULT_MAX, Number(props.available)));
});

function adjustQty(delta) {
  const next = Math.max(0, Math.min(maxQty.value, Number(props.qty) + delta));
  emit("update:qty", next);
}

function handleQtyInput(value) {
  const parsed = Number.parseInt(String(value), 10);
  if (!Number.isNaN(parsed)) {
    emit("update:qty", Math.max(0, Math.min(maxQty.value, parsed)));
  } else {
    emit("update:qty", props.qty);
  }
}

const avgPerNight = computed(() => {
  if (!props.preview || !props.nights) return Number(props.room.base_rate ?? 0);
  const sub = Number(props.preview.subtotal || 0);
  if (sub <= 0) return Number(props.room.base_rate ?? 0);
  return Math.round(sub / props.nights);
});

const availabilityState = computed(() => {
  if (props.available == null) return null;
  if (props.available === 0) return { label: "Sold out", tone: "destructive" };
  if (props.available <= 3) return { label: `Only ${props.available} left`, tone: "warning" };
  return { label: `${props.available} available`, tone: "success" };
});

const specChips = computed(() => {
  const chips = [];
  if (props.room.bed_type) chips.push({ icon: "hugeicons:bed-single-01", text: props.room.bed_type });
  if (props.room.max_pax) chips.push({ icon: "hugeicons:user-multiple-02", text: `${props.room.max_pax} pax` });
  if (props.room.area_sqm) chips.push({ icon: "hugeicons:resize-01", text: `${props.room.area_sqm} m²` });
  if (props.room.breakfast_included) chips.push({ icon: "hugeicons:coffee-02", text: "Breakfast" });
  return chips;
});

const heroImage = computed(() => {
  const gallery = props.room.gallery ?? [];
  if (!gallery.length) return null;
  const first = gallery[0];
  return first.md || first.lg || first.url || first.sm || null;
});

const fmtRupiah = (n) => new Intl.NumberFormat("id-ID").format(Number(n) || 0);
</script>

<template>
  <div
    class="bg-card overflow-hidden rounded-2xl border transition-colors"
    :class="qty > 0 ? 'border-primary/40 ring-primary/10 ring-2' : 'border-border'"
  >
    <div class="grid gap-0 sm:grid-cols-[10rem_1fr]">
      <Lightbox
        v-if="room.gallery?.length"
        :items="room.gallery"
        :alt="room.name"
        rounded="rounded-none"
      >
        <template #trigger="{ open }">
          <button
            type="button"
            class="bg-muted relative aspect-[4/3] w-full overflow-hidden sm:aspect-auto sm:h-full sm:min-h-[8rem]"
            @click="open"
          >
            <img
              :src="heroImage"
              :alt="room.name"
              loading="lazy"
              class="size-full cursor-zoom-in object-cover"
            />
            <span
              v-if="room.gallery.length > 1"
              class="bg-background/90 absolute right-1.5 bottom-1.5 inline-flex items-center gap-x-1 rounded-full px-2 py-0.5 text-xs tracking-tight backdrop-blur-sm"
            >
              <Icon name="hugeicons:image-02" class="size-3.5" />
              {{ room.gallery.length }}
            </span>
          </button>
        </template>
      </Lightbox>
      <div
        v-else
        class="from-muted to-muted/40 aspect-[4/3] w-full bg-linear-to-br sm:aspect-auto sm:h-full sm:min-h-[8rem]"
      />

      <div class="flex flex-col gap-2 p-3 sm:gap-2.5 sm:p-4">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <h3 class="text-sm font-medium tracking-tight sm:text-base">{{ room.name }}</h3>
            <div v-if="specChips.length" class="text-muted-foreground mt-1 flex flex-wrap gap-x-3 gap-y-1">
              <span
                v-for="spec in specChips"
                :key="spec.text"
                class="inline-flex items-center gap-x-1 text-xs tracking-tight"
              >
                <Icon :name="spec.icon" class="size-3.5 shrink-0" />
                {{ spec.text }}
              </span>
            </div>
          </div>
          <div class="text-right tracking-tight">
            <div class="text-sm font-semibold tabular-nums sm:text-base">
              Rp{{ fmtRupiah(avgPerNight) }}
            </div>
            <div class="text-muted-foreground text-xs tracking-tight">per night</div>
          </div>
        </div>

        <div v-if="room.amenities?.length" class="flex flex-wrap gap-1">
          <span
            v-for="a in room.amenities.slice(0, 6)"
            :key="a"
            class="bg-muted/60 rounded-md px-1.5 py-0.5 text-xs tracking-tight"
          >
            {{ a }}
          </span>
          <span
            v-if="room.amenities.length > 6"
            class="text-muted-foreground rounded-md px-1.5 py-0.5 text-xs tracking-tight"
          >
            +{{ room.amenities.length - 6 }} more
          </span>
        </div>

        <p
          v-if="room.description"
          class="text-muted-foreground line-clamp-2 text-xs tracking-tight sm:text-sm"
        >
          {{ room.description }}
        </p>

        <div class="mt-auto flex items-center justify-between gap-3 pt-1">
          <div
            v-if="availabilityState"
            class="inline-flex items-center gap-x-1 text-xs tracking-tight"
            :class="{
              'text-destructive-foreground': availabilityState.tone === 'destructive',
              'text-warning-foreground': availabilityState.tone === 'warning',
              'text-success-foreground': availabilityState.tone === 'success',
            }"
          >
            <span
              class="inline-block size-1.5 rounded-full"
              :class="{
                'bg-destructive': availabilityState.tone === 'destructive',
                'bg-warning': availabilityState.tone === 'warning',
                'bg-success': availabilityState.tone === 'success',
              }"
            />
            {{ availabilityState.label }}
          </div>
          <div v-else />

          <ButtonGroup>
            <Input
              :model-value="qty"
              :max-length="2"
              :disabled="maxQty === 0"
              class="h-9 !w-14 text-center font-mono"
              :aria-label="`Quantity for ${room.name}`"
              @update:model-value="handleQtyInput"
            />
            <Button
              variant="outline"
              size="icon"
              type="button"
              :aria-label="`Decrement ${room.name}`"
              :disabled="qty <= 0 || maxQty === 0"
              @click="adjustQty(-1)"
            >
              <Icon name="hugeicons:minus-sign" class="size-4" />
            </Button>
            <Button
              variant="outline"
              size="icon"
              type="button"
              :aria-label="`Increment ${room.name}`"
              :disabled="qty >= maxQty || maxQty === 0"
              @click="adjustQty(1)"
            >
              <Icon name="hugeicons:plus-sign" class="size-4" />
            </Button>
          </ButtonGroup>
        </div>
      </div>
    </div>

    <div v-if="qty > 0" class="border-border space-y-1.5 border-t bg-muted/30 px-3 py-2.5 sm:px-4">
      <Label class="text-muted-foreground text-xs tracking-tight">
        Notes for this room (optional)
      </Label>
      <Textarea
        :model-value="notes"
        rows="2"
        placeholder="e.g. extra bed, high floor, late check-in..."
        maxlength="1000"
        class="bg-background"
        @update:model-value="(v) => emit('update:notes', v ?? '')"
      />
    </div>
  </div>
</template>
