<template>
  <!-- Section (layout block, no input) -->
  <div v-if="field.type === 'section'" class="border-border border-t pt-6 first:border-t-0 first:pt-0">
    <h2 class="text-lg font-semibold tracking-tighter">{{ field.label }}</h2>
    <div
      v-if="field.settings?.description"
      class="prose prose-sm text-muted-foreground mt-1.5 max-w-none text-sm tracking-tight"
      v-html="field.settings.description"
    />
  </div>

  <div v-else class="space-y-2">
    <Label :for="fieldId" class="text-sm sm:text-base">
      {{ field.label }}
      <span v-if="isRequired" class="text-destructive">*</span>
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

    <!-- Rich Text -->
    <TipTapEditor
      v-else-if="field.type === 'rich_text'"
      :model-value="modelValue || ''"
      :placeholder="field.placeholder || 'Write your answer'"
      :sticky="false"
      :allow-images="false"
      min-height="140px"
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
    <InputNumber
      v-else-if="field.type === 'number'"
      :id="fieldId"
      :model-value="modelValue"
      decimal
      :placeholder="field.placeholder"
      :min="field.validation?.min"
      :max="field.validation?.max"
      :class="{ 'border-destructive': error }"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Phone -->
    <InputPhone
      v-else-if="field.type === 'phone'"
      :id="fieldId"
      :model-value="modelValue || ''"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- URL -->
    <InputLink
      v-else-if="field.type === 'url'"
      :model-value="modelValue || ''"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Date -->
    <DatePicker
      v-else-if="field.type === 'date'"
      :model-value="parseLocalDateString(modelValue)"
      :placeholder="field.placeholder || 'Pick a date'"
      @update:model-value="$emit('update:modelValue', $event ? toLocalDateString($event) : null)"
    />

    <!-- Time -->
    <TimePicker
      v-else-if="field.type === 'time'"
      :model-value="parseTimeString(modelValue)"
      clearable
      @update:model-value="$emit('update:modelValue', $event ? formatTimeValue($event) : null)"
    />

    <!-- Date & Time -->
    <DatePicker
      v-else-if="field.type === 'datetime'"
      with-time
      :model-value="parseDateTimeString(modelValue)"
      :placeholder="field.placeholder || 'Pick date and time'"
      @update:model-value="$emit('update:modelValue', $event ? toDateTimeString($event) : null)"
    />

    <!-- Date Range -->
    <RangeCalendarPicker
      v-else-if="field.type === 'date_range'"
      :model-value="dateRangeValue"
      :placeholder="field.placeholder || 'Pick a date range'"
      @update:model-value="handleDateRange"
    />

    <!-- Select -->
    <Select
      v-else-if="field.type === 'select'"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <SelectTrigger :id="fieldId" class="w-full" :class="{ 'border-destructive': error }">
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

    <!-- Multi Select -->
    <MultiSelect
      v-else-if="field.type === 'multi_select'"
      :model-value="multiSelectValue"
      :options="selectOptions"
      :placeholder="field.placeholder || 'Select options'"
      open-on-click
      @update:model-value="$emit('update:modelValue', $event.map((o) => o.value))"
    />

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

    <!-- Switch -->
    <div v-else-if="field.type === 'switch'" class="flex items-center gap-x-2">
      <Switch
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
          @update:model-value="handleMultiCheck($event, opt.value || opt)"
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

    <!-- Tags -->
    <TagsInput
      v-else-if="field.type === 'tags'"
      :model-value="modelValue || []"
      :max="field.validation?.max_selections"
      class="text-sm"
      @update:model-value="$emit('update:modelValue', $event)"
    >
      <TagsInputItem v-for="tag in modelValue || []" :key="tag" :value="tag">
        <TagsInputItemText />
        <TagsInputItemDelete />
      </TagsInputItem>
      <TagsInputInput :placeholder="field.placeholder || 'Type and press Enter'" />
    </TagsInput>

    <!-- Country -->
    <LocationCombobox
      v-else-if="field.type === 'country'"
      :model-value="modelValue"
      :options="countries"
      :pinned="['Indonesia']"
      :placeholder="field.placeholder || 'Select country'"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Color -->
    <div v-else-if="field.type === 'color'" class="flex items-center gap-x-2">
      <label
        class="border-input relative size-9 shrink-0 cursor-pointer overflow-hidden rounded-md border shadow-xs"
        :style="{ backgroundColor: isValidHex(modelValue) ? modelValue : '#ffffff' }"
      >
        <input
          :id="fieldId"
          type="color"
          :value="isValidHex(modelValue) ? modelValue : '#000000'"
          class="absolute inset-0 size-full cursor-pointer opacity-0"
          @input="$emit('update:modelValue', $event.target.value)"
        />
      </label>
      <Input
        :model-value="modelValue"
        placeholder="#000000"
        maxlength="7"
        class="w-32 font-mono"
        :class="{ 'border-destructive': error }"
        @update:model-value="$emit('update:modelValue', $event)"
      />
    </div>

    <!-- File -->
    <div v-else-if="field.type === 'file'" class="space-y-2">
      <input
        ref="fileInputRef"
        type="file"
        class="hidden"
        :accept="acceptAttribute"
        :multiple="isMultipleFile"
        @change="handleFileSelect"
      />
      <div
        class="rounded-md border border-dashed p-4 transition-colors"
        :class="[
          isDragging ? 'border-primary bg-primary/5' : 'border-border',
          { 'border-destructive': error && !isDragging },
        ]"
        @dragover.prevent="!preview && (isDragging = true)"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <div class="flex flex-col items-center gap-y-2 text-center">
          <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="preview || uploadingFile || maxFilesReached"
            @click="fileInputRef?.click()"
          >
            <Spinner v-if="uploadingFile" class="size-4" />
            <Icon v-else name="lucide:paperclip" class="size-4" />
            <span>{{ uploadingFile ? "Uploading..." : isMultipleFile ? "Add file" : "Choose file" }}</span>
          </Button>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">or drag and drop here</p>
        </div>
        <div v-if="uploadingFile" class="mt-3 space-y-1">
          <div class="bg-muted h-1.5 w-full overflow-hidden rounded-full">
            <div
              class="bg-primary h-full rounded-full transition-[width] duration-150"
              :style="{ width: `${uploadProgress}%` }"
            />
          </div>
          <p class="text-muted-foreground truncate text-xs tracking-tight">
            {{ uploadingName }} - {{ uploadProgress }}%
          </p>
        </div>
      </div>
      <ul v-if="uploadedFiles.length" class="space-y-1.5">
        <li
          v-for="file in uploadedFiles"
          :key="file.folder"
          class="bg-muted/50 border-border flex items-center gap-x-2 rounded-md border px-3 py-2"
        >
          <Icon name="lucide:file" class="text-muted-foreground size-4 shrink-0" />
          <span class="min-w-0 flex-1 truncate text-sm tracking-tight">{{ file.name }}</span>
          <span class="text-muted-foreground shrink-0 text-xs">{{ formatFileSize(file.size) }}</span>
          <button
            type="button"
            class="text-muted-foreground hover:text-destructive shrink-0 rounded p-0.5 transition-colors"
            @click="removeFile(file)"
          >
            <Icon name="lucide:x" class="size-4" />
          </button>
        </li>
      </ul>
      <p v-if="fileConstraintText" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
        {{ fileConstraintText }}
      </p>
      <p v-if="fileError" class="text-destructive text-sm tracking-tight">{{ fileError }}</p>
    </div>

    <!-- Rating -->
    <div v-else-if="field.type === 'rating'" class="flex gap-x-1">
      <button
        v-for="star in ratingMax"
        :key="star"
        type="button"
        @click="$emit('update:modelValue', star)"
        class="transition-colors"
        :class="star <= (modelValue || 0) ? 'text-warning' : 'text-muted-foreground/40'"
        :aria-label="`${star} of ${ratingMax}`"
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

    <!-- Slider -->
    <div v-else-if="field.type === 'slider'" class="space-y-2 pt-1">
      <Slider
        :model-value="[Number(modelValue ?? sliderMin)]"
        :min="sliderMin"
        :max="sliderMax"
        :step="sliderStep"
        @update:model-value="$emit('update:modelValue', $event?.[0] ?? null)"
      />
      <div class="text-muted-foreground flex justify-between text-xs tracking-tight sm:text-sm">
        <span>{{ sliderMin }}</span>
        <span class="text-foreground font-medium">{{ modelValue ?? sliderMin }}</span>
        <span>{{ sliderMax }}</span>
      </div>
    </div>

    <!-- Linear Scale -->
    <div v-else-if="field.type === 'linear_scale'" class="space-y-2">
      <div role="radiogroup" class="flex flex-wrap gap-1.5 sm:gap-2">
        <button
          v-for="n in scaleRange"
          :key="n"
          type="button"
          role="radio"
          :aria-checked="Number(modelValue) === n"
          :aria-label="String(n)"
          class="flex size-9 items-center justify-center rounded-lg border text-sm font-medium tracking-tight transition-colors active:scale-95 sm:size-10"
          :class="
            Number(modelValue) === n
              ? 'border-primary bg-primary text-primary-foreground'
              : 'border-input hover:bg-muted'
          "
          @click="$emit('update:modelValue', n)"
        >
          {{ n }}
        </button>
      </div>
      <div
        v-if="field.settings?.min_label || field.settings?.max_label"
        class="text-muted-foreground flex justify-between text-xs tracking-tight sm:text-sm"
      >
        <span>{{ field.settings?.min_label }}</span>
        <span>{{ field.settings?.max_label }}</span>
      </div>
    </div>

    <!-- Help text -->
    <p v-if="field.help_text" class="text-muted-foreground text-xs tracking-tight sm:text-sm">
      {{ field.help_text }}
    </p>

    <!-- Error -->
    <p v-if="error" class="text-destructive text-sm tracking-tight">{{ error }}</p>
  </div>
</template>

<script setup>
import countries from "@/data/countries.json";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { Input } from "@/components/ui/input";
import { InputLink } from "@/components/ui/input-link";
import { InputNumber } from "@/components/ui/input-number";
import { InputPhone } from "@/components/ui/input-phone";
import { Label } from "@/components/ui/label";
import { LocationCombobox } from "@/components/ui/location-combobox";
import { MultiSelect } from "@/components/ui/multi-select";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { RangeCalendarPicker } from "@/components/ui/range-calendar-picker";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Slider } from "@/components/ui/slider";
import { Switch } from "@/components/ui/switch";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { Textarea } from "@/components/ui/textarea";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { parseLocalDateString, toLocalDateString } from "@/lib/utils";
import { Time } from "@internationalized/date";

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { default: null },
  error: { type: String, default: null },
  formSlug: { type: String, default: null },
  preview: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "uploading"]);

const fieldId = computed(() => `field-${props.field.ulid}`);

const isRequired = computed(() => !!props.field.validation?.required);

/* ----- Options ----- */
const selectOptions = computed(() =>
  (props.field.options || []).map((opt) => ({
    value: String(opt.value ?? opt),
    label: String(opt.label ?? opt.value ?? opt),
  }))
);

const multiSelectValue = computed(() =>
  (props.modelValue || []).map(
    (v) => selectOptions.value.find((o) => o.value === v) || { value: v, label: v }
  )
);

const handleMultiCheck = (checked, value) => {
  const current = props.modelValue || [];
  if (checked) {
    emit("update:modelValue", [...current, value]);
  } else {
    emit(
      "update:modelValue",
      current.filter((v) => v !== value)
    );
  }
};

/* ----- Date & time ----- */
const parseTimeString = (value) => {
  if (!value) return null;
  const match = /^(\d{1,2}):(\d{2})/.exec(value);
  if (!match) return null;
  return new Time(Number(match[1]), Number(match[2]));
};

const formatTimeValue = (time) =>
  `${String(time.hour).padStart(2, "0")}:${String(time.minute).padStart(2, "0")}`;

const parseDateTimeString = (value) => {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(value);
  if (!match) return null;
  const [, y, m, d, h, min] = match;
  return new Date(Number(y), Number(m) - 1, Number(d), Number(h), Number(min));
};

const toDateTimeString = (date) => {
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${toLocalDateString(date)} ${hours}:${minutes}`;
};

const dateRangeValue = computed(() => {
  const value = props.modelValue;
  if (!value || typeof value !== "object") return null;
  return {
    start: parseLocalDateString(value.start),
    end: parseLocalDateString(value.end),
  };
});

const handleDateRange = (range) => {
  if (!range || (!range.start && !range.end)) {
    emit("update:modelValue", null);
    return;
  }
  if (range.start && range.end) {
    emit("update:modelValue", {
      start: toLocalDateString(range.start),
      end: toLocalDateString(range.end),
    });
  }
};

/* ----- Scales ----- */
const ratingMax = computed(() => Number(props.field.settings?.max) || 5);

const scaleRange = computed(() => {
  const min = props.field.validation?.min ?? 1;
  const max = props.field.validation?.max ?? 5;
  const range = [];
  for (let i = min; i <= max; i++) {
    range.push(i);
  }
  return range;
});

const sliderMin = computed(() => Number(props.field.validation?.min ?? 0));
const sliderMax = computed(() => Number(props.field.validation?.max ?? 100));
const sliderStep = computed(() => Number(props.field.settings?.step) || 1);

/* ----- Color ----- */
const isValidHex = (value) => /^#[0-9a-fA-F]{6}$/.test(value || "");

/* ----- File upload ----- */
const fileInputRef = ref(null);
const uploadingFile = ref(false);
const fileError = ref(null);
const uploadedFiles = ref([]);

const isMultipleFile = computed(() => !!props.field.settings?.multiple);
const maxFiles = computed(() => Number(props.field.validation?.max_files) || 5);
const maxFilesReached = computed(
  () => isMultipleFile.value && uploadedFiles.value.length >= maxFiles.value
);

const allowedExtensions = computed(() =>
  (props.field.validation?.allowed_file_types || []).map((ext) =>
    String(ext).toLowerCase().replace(/^\./, "")
  )
);

const acceptAttribute = computed(() =>
  allowedExtensions.value.length ? allowedExtensions.value.map((e) => `.${e}`).join(",") : undefined
);

const maxFileSizeKb = computed(() => Number(props.field.validation?.max_file_size) || 20480);

const fileConstraintText = computed(() => {
  const parts = [];
  if (allowedExtensions.value.length) parts.push(allowedExtensions.value.join(", ").toUpperCase());
  parts.push(`max ${formatFileSize(maxFileSizeKb.value * 1024)}`);
  if (isMultipleFile.value) parts.push(`up to ${maxFiles.value} files`);
  return parts.join(" · ");
});

const formatFileSize = (bytes) => {
  if (!bytes) return "";
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const syncFileModel = () => {
  const folders = uploadedFiles.value.map((f) => f.folder);
  emit("update:modelValue", isMultipleFile.value ? folders : folders[0] || null);
};

const isDragging = ref(false);
const uploadProgress = ref(0);
const uploadingName = ref(null);

const handleDrop = (event) => {
  isDragging.value = false;
  if (props.preview) return;
  processFiles(Array.from(event.dataTransfer?.files || []));
};

const handleFileSelect = (event) => {
  const files = Array.from(event.target.files || []);
  event.target.value = "";
  processFiles(files);
};

const processFiles = async (files) => {
  if (!files.length || !props.formSlug || props.preview) return;

  fileError.value = null;

  for (const file of files) {
    if (isMultipleFile.value && uploadedFiles.value.length >= maxFiles.value) {
      fileError.value = `You can upload up to ${maxFiles.value} files.`;
      break;
    }

    const extension = file.name.split(".").pop()?.toLowerCase();
    if (allowedExtensions.value.length && !allowedExtensions.value.includes(extension)) {
      fileError.value = `"${file.name}" is not an accepted file type.`;
      continue;
    }

    if (file.size > maxFileSizeKb.value * 1024) {
      fileError.value = `"${file.name}" exceeds the ${formatFileSize(maxFileSizeKb.value * 1024)} limit.`;
      continue;
    }

    await uploadFile(file);
  }
};

// XMLHttpRequest instead of $fetch so we can report upload progress
const xhrUpload = (url, formData, onProgress) =>
  new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", url);

    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100));
      }
    };

    xhr.onload = () => {
      let data = null;
      try {
        data = JSON.parse(xhr.responseText);
      } catch {
        data = null;
      }

      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(data);
      } else {
        reject({ data });
      }
    };

    xhr.onerror = () => reject(new Error("Upload failed"));
    xhr.send(formData);
  });

const uploadFile = async (file) => {
  uploadingFile.value = true;
  uploadProgress.value = 0;
  uploadingName.value = file.name;
  emit("uploading", true);

  try {
    const formData = new FormData();
    formData.append("file", file);
    formData.append("field", props.field.ulid);

    const apiUrl = useRuntimeConfig().public.apiUrl;
    const response = await xhrUpload(
      `${apiUrl}/api/public/forms/${props.formSlug}/upload`,
      formData,
      (progress) => {
        uploadProgress.value = progress;
      }
    );

    if (!isMultipleFile.value && uploadedFiles.value.length) {
      await revertFolder(uploadedFiles.value[0].folder);
      uploadedFiles.value = [];
    }

    uploadedFiles.value.push({ folder: response.folder, name: file.name, size: file.size });
    syncFileModel();
  } catch (e) {
    fileError.value =
      e?.data?.errors?.file?.[0] || e?.data?.message || "Upload failed. Please try again.";
  } finally {
    uploadingFile.value = false;
    uploadingName.value = null;
    emit("uploading", false);
  }
};

const removeFile = async (file) => {
  uploadedFiles.value = uploadedFiles.value.filter((f) => f.folder !== file.folder);
  syncFileModel();
  await revertFolder(file.folder);
};

const revertFolder = async (folder) => {
  try {
    const apiUrl = useRuntimeConfig().public.apiUrl;
    await $fetch(`${apiUrl}/api/public/forms/${props.formSlug}/upload`, {
      method: "DELETE",
      body: folder,
    });
  } catch {
    // Temp uploads are cleaned up server-side eventually; ignore revert errors.
  }
};
</script>
