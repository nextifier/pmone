<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold tracking-tight">Available Rooms</h2>
      <span
        v-if="checking"
        class="text-muted-foreground text-xs tracking-tight"
        aria-live="polite"
      >
        Checking availability…
      </span>
    </div>

    <div
      v-if="!rooms?.length"
      class="text-muted-foreground rounded-md border border-dashed py-8 text-center text-sm tracking-tight"
    >
      No rooms available.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="room in rooms"
        :key="room.id"
        class="rounded-md border overflow-hidden"
      >
        <div v-if="room.gallery?.length" class="bg-muted">
          <div class="grid grid-cols-4 gap-0.5">
            <div
              v-for="(img, idx) in room.gallery.slice(0, 4)"
              :key="img.id"
              :class="[
                'bg-muted aspect-square overflow-hidden',
                idx === 0 ? 'col-span-2 row-span-2 aspect-auto' : '',
              ]"
            >
              <img
                :src="img.md || img.url"
                :alt="room.name"
                class="size-full object-cover"
                loading="lazy"
                decoding="async"
              />
            </div>
          </div>
        </div>

        <div class="space-y-3 p-4 sm:p-5">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0 flex-1 space-y-1.5">
              <h3 class="text-base font-semibold tracking-tight">{{ room.name }}</h3>
              <p v-if="roomMeta(room)" class="text-muted-foreground text-xs sm:text-sm tracking-tight">
                {{ roomMeta(room) }}
              </p>
              <div v-if="room.amenities?.length" class="flex flex-wrap gap-1 pt-1">
                <span
                  v-for="a in room.amenities"
                  :key="a"
                  class="bg-muted rounded-full px-2 py-0.5 text-xs tracking-tight"
                >
                  {{ a }}
                </span>
              </div>
            </div>
            <PriceDisplay :base-rate="room.base_rate" :all-in-rate="room.all_in_rate" />
          </div>

          <div
            v-if="availabilityLabel(room)"
            class="text-xs tracking-tight"
            :class="availabilityClass(room)"
          >
            {{ availabilityLabel(room) }}
          </div>

          <div class="flex items-center justify-between gap-3 border-t pt-3">
            <Label class="text-xs sm:text-sm tracking-tight">Quantity</Label>
            <NumberField
              :model-value="modelValue[room.id] ?? 0"
              :min="0"
              :max="maxFor(room)"
              :disabled="maxFor(room) === 0"
              class="w-32"
              @update:model-value="updateQty(room.id, $event)"
            >
              <NumberFieldContent>
                <NumberFieldDecrement />
                <NumberFieldInput />
                <NumberFieldIncrement />
              </NumberFieldContent>
            </NumberField>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import PriceDisplay from "./PriceDisplay.vue";
import { Label } from "@/components/ui/label";
import {
  NumberField,
  NumberFieldContent,
  NumberFieldDecrement,
  NumberFieldIncrement,
  NumberFieldInput,
} from "@/components/ui/number-field";

const props = defineProps({
  rooms: { type: Array, default: () => [] },
  modelValue: { type: Object, default: () => ({}) },
  availability: { type: Object, default: () => ({}) },
  checking: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const DEFAULT_MAX = 20;

const maxFor = (room) => {
  const avail = props.availability?.[room.id];
  if (avail == null) return DEFAULT_MAX;
  return Math.max(0, Math.min(DEFAULT_MAX, Number(avail)));
};

const availabilityLabel = (room) => {
  const avail = props.availability?.[room.id];
  if (avail == null) return "";
  if (avail === 0) return "Sold out";
  if (avail <= 3) return `Only ${avail} left`;
  return "";
};

const availabilityClass = (room) => {
  const avail = props.availability?.[room.id];
  if (avail === 0) return "text-destructive font-medium";
  if (avail != null && avail <= 3) return "text-warning font-medium";
  return "text-muted-foreground";
};

const updateQty = (roomId, value) => {
  emit("update:modelValue", { ...props.modelValue, [roomId]: Number(value) || 0 });
};

const roomMeta = (room) => {
  const parts = [];
  if (room.bed_type) parts.push(room.bed_type);
  if (room.max_pax) parts.push(`max ${room.max_pax} pax`);
  if (room.area_sqm) parts.push(`${room.area_sqm} m²`);
  return parts.join(" · ");
};
</script>
