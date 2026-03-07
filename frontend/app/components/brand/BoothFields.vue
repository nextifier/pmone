<template>
  <div class="grid grid-cols-2 gap-x-2 gap-y-4">
    <div class="space-y-2">
      <Label>Booth Type</Label>
      <Select v-model="form.booth_type">
        <SelectTrigger class="w-full">
          <SelectValue placeholder="Select type" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="raw_space">Raw Space</SelectItem>
          <SelectItem value="standard_shell_scheme">Standard Shell Scheme</SelectItem>
          <SelectItem value="enhanced_shell_scheme">Enhanced Shell Scheme</SelectItem>
          <SelectItem value="table_chair_only">Table &amp; Chair Only</SelectItem>
        </SelectContent>
      </Select>
    </div>
    <div class="space-y-2">
      <Label>{{ boothNumberLabel }}</Label>
      <Input v-model="form.booth_number" :placeholder="boothNumberPlaceholder" />
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
          class="flex-1 rounded-none border-0 shadow-none focus-visible:ring-0 focus-visible:ring-transparent dark:bg-transparent"
        />
        <InputGroupAddon>
          <InputGroupText>Rp</InputGroupText>
        </InputGroupAddon>
      </InputGroup>
    </div>
  </div>
</template>

<script setup>
defineProps({
  form: { type: Object, required: true },
  boothNumberLabel: { type: String, default: "Booth Number" },
  boothNumberPlaceholder: { type: String, default: "Ex: B-101" },
});
</script>
