<template>
  <div class="space-y-1.5">
    <Label :for="fieldId">
      {{ field.label }}
      <span v-if="field.validation?.required" class="text-destructive">*</span>
    </Label>

    <!-- Text -->
    <Input
      v-if="field.type === 'text'"
      :id="fieldId"
      :model-value="modelValue"
      type="text"
      :placeholder="field.placeholder"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Textarea -->
    <Textarea
      v-else-if="field.type === 'textarea'"
      :id="fieldId"
      :model-value="modelValue"
      rows="3"
      :placeholder="field.placeholder"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Email -->
    <Input
      v-else-if="field.type === 'email'"
      :id="fieldId"
      :model-value="modelValue"
      type="email"
      :placeholder="field.placeholder || 'email@example.com'"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Number -->
    <Input
      v-else-if="field.type === 'number'"
      :id="fieldId"
      :model-value="modelValue"
      type="number"
      :placeholder="field.placeholder"
      :min="field.validation?.min"
      :max="field.validation?.max"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', Number($event))"
    />

    <!-- Phone -->
    <Input
      v-else-if="field.type === 'phone'"
      :id="fieldId"
      :model-value="modelValue"
      type="tel"
      :placeholder="field.placeholder || '+62'"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- URL -->
    <Input
      v-else-if="field.type === 'url'"
      :id="fieldId"
      :model-value="modelValue"
      type="url"
      :placeholder="field.placeholder || 'https://'"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Date -->
    <Input
      v-else-if="field.type === 'date'"
      :id="fieldId"
      :model-value="modelValue"
      type="date"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Time -->
    <Input
      v-else-if="field.type === 'time'"
      :id="fieldId"
      :model-value="modelValue"
      type="time"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Select -->
    <Select
      v-else-if="field.type === 'select'"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <SelectTrigger :id="fieldId" :class="{ 'border-destructive': error }">
        <SelectValue :placeholder="field.placeholder || 'Select an option'" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="opt in field.options"
          :key="opt.value || opt"
          :value="String(opt.value || opt)"
        >
          {{ opt.label || opt }}
        </SelectItem>
      </SelectContent>
    </Select>

    <!-- Multi Select (checkbox list) -->
    <div v-else-if="field.type === 'multi_select'" class="space-y-2">
      <div
        v-for="opt in field.options"
        :key="opt.value || opt"
        class="flex items-center gap-x-2"
      >
        <Checkbox
          :id="`${fieldId}-${opt.value || opt}`"
          :model-value="(modelValue || []).includes(opt.value || opt)"
          @update:model-value="handleMultiSelect($event, opt.value || opt)"
        />
        <Label :for="`${fieldId}-${opt.value || opt}`" class="font-normal">
          {{ opt.label || opt }}
        </Label>
      </div>
    </div>

    <!-- Checkbox (single) -->
    <div v-else-if="field.type === 'checkbox'" class="flex items-center gap-x-2">
      <Checkbox
        :id="fieldId"
        :model-value="!!modelValue"
        @update:model-value="$emit('update:modelValue', !!$event)"
      />
      <Label :for="fieldId" class="font-normal">
        {{ field.placeholder || field.label }}
      </Label>
    </div>

    <!-- Checkbox Group -->
    <div v-else-if="field.type === 'checkbox_group'" class="space-y-2">
      <div
        v-for="opt in field.options"
        :key="opt.value || opt"
        class="flex items-center gap-x-2"
      >
        <Checkbox
          :id="`${fieldId}-${opt.value || opt}`"
          :model-value="(modelValue || []).includes(opt.value || opt)"
          @update:model-value="handleMultiSelect($event, opt.value || opt)"
        />
        <Label :for="`${fieldId}-${opt.value || opt}`" class="font-normal">
          {{ opt.label || opt }}
        </Label>
      </div>
    </div>

    <!-- Radio -->
    <RadioGroup
      v-else-if="field.type === 'radio'"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <div
        v-for="opt in field.options"
        :key="opt.value || opt"
        class="flex items-center gap-x-2"
      >
        <RadioGroupItem
          :value="String(opt.value || opt)"
          :id="`${fieldId}-${opt.value || opt}`"
        />
        <Label :for="`${fieldId}-${opt.value || opt}`" class="font-normal">
          {{ opt.label || opt }}
        </Label>
      </div>
    </RadioGroup>

    <!-- File -->
    <div v-else-if="field.type === 'file'" class="space-y-2">
      <Input
        :id="fieldId"
        type="file"
          :class="{ 'border-destructive': error }"
        @change="handleFileUpload($event)"
      />
      <div v-if="uploadingFile" class="text-muted-foreground flex items-center gap-x-2 text-xs">
        <Spinner class="size-3" />
        <span>Uploading...</span>
      </div>
    </div>

    <!-- Rating (5 stars) -->
    <div v-else-if="field.type === 'rating'" class="flex gap-x-1">
      <button
        v-for="star in 5"
        :key="star"
        type="button"
        @click="$emit('update:modelValue', star)"
        class="transition-colors"
        :class="star <= (modelValue || 0) ? 'text-amber-400' : 'text-gray-400'"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          class="size-7 transition-colors"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
          :fill="star <= (modelValue || 0) ? 'currentColor' : 'none'"
        >
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </svg>
      </button>
    </div>

    <!-- Linear Scale -->
    <RadioGroup
      v-else-if="field.type === 'linear_scale'"
      :model-value="modelValue != null ? String(modelValue) : undefined"
      @update:model-value="$emit('update:modelValue', Number($event))"
      class="flex flex-wrap gap-2"
    >
      <div
        v-for="n in scaleRange"
        :key="n"
        class="flex flex-col items-center gap-y-1"
      >
        <RadioGroupItem :value="String(n)" :id="`${fieldId}-${n}`" />
        <Label :for="`${fieldId}-${n}`" class="text-xs font-normal">{{ n }}</Label>
      </div>
    </RadioGroup>

    <!-- Help text -->
    <p v-if="field.help_text" class="text-muted-foreground text-xs">{{ field.help_text }}</p>

    <!-- Error -->
    <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
  </div>
</template>

<script setup>
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: null },
  error: { type: String, default: null },
  formSlug: { type: String, default: null },
});

const emit = defineEmits(["update:modelValue"]);

const fieldId = computed(() => `field-${props.field.ulid}`);
const uploadingFile = ref(false);

const scaleRange = computed(() => {
  const min = props.field.validation?.min || 1;
  const max = props.field.validation?.max || 5;
  const range = [];
  for (let i = min; i <= max; i++) {
    range.push(i);
  }
  return range;
});

const handleMultiSelect = (checked, value) => {
  const current = props.modelValue || [];
  if (checked) {
    emit("update:modelValue", [...current, value]);
  } else {
    emit("update:modelValue", current.filter((v) => v !== value));
  }
};

const handleFileUpload = async (event) => {
  const file = event.target.files?.[0];
  if (!file || !props.formSlug) return;

  uploadingFile.value = true;
  try {
    const formData = new FormData();
    formData.append("file", file);

    const apiUrl = useRuntimeConfig().public.apiUrl;
    const response = await $fetch(`${apiUrl}/api/public/forms/${props.formSlug}/upload`, {
      method: "POST",
      body: formData,
    });

    emit("update:modelValue", response.folder);
  } catch (e) {
    console.error("Failed to upload file:", e);
  } finally {
    uploadingFile.value = false;
  }
};
</script>
