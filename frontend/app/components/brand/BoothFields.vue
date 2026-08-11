<template>
  <div class="grid grid-cols-2 gap-x-2 gap-y-4">
    <div class="space-y-2">
      <Label>Booth Type</Label>
      <Select v-model="form.booth_type">
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem v-for="option in BOOTH_TYPE_OPTIONS" :key="option.value" :value="option.value">
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
    <div class="space-y-2">
      <Label>{{ boothNumberLabel }}</Label>
      <BrandInputBoothNumber
        v-model="form.booth_number"
        :placeholder="boothNumberPlaceholder"
      />
    </div>
    <div class="space-y-2">
      <Label>Booth Size (sqm)</Label>
      <InputNumber
        v-model="form.booth_size"
        :min="0"
        decimal
        placeholder="Ex: 9.00"
      />
    </div>
    <div class="space-y-2">
      <Label>Booth Price</Label>
      <InputGroup>
        <InputNumber
          v-model="form.booth_price"
          :min="0"
          placeholder="Ex: 50,000,000"
          data-slot="input-group-control"
          class="cn-input-group-input flex-1"
        />
        <InputGroupAddon>
          <InputGroupText>Rp</InputGroupText>
        </InputGroupAddon>
      </InputGroup>
    </div>
    <div class="space-y-2">
      <Label>Order Currency</Label>
      <Select v-model="form.currency_override">
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select currency" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in CURRENCY_OVERRIDE_OPTIONS"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>

    <!-- Order Currency is the last cell of an even grid, so it leaves a hole on
         its right. Call sites that have one more booth-scoped field (Sales in
         the add-brand dialog) drop it in here rather than opening a new row. -->
    <slot />
  </div>
</template>

<script setup>
defineProps({
  form: { type: Object, required: true },
  boothNumberLabel: { type: String, default: "Booth Number" },
  boothNumberPlaceholder: { type: String, default: "Ex: B-101" },
});
</script>
