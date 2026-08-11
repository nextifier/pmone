<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-6">
    <!-- Event Title -->
    <Field :data-invalid="!!errors?.title">
      <FieldLabel for="title">Event Title</FieldLabel>
      <Input id="title" v-model="form.title" type="text" required :aria-invalid="!!errors?.title" />
      <FieldError :errors="errors.title" />
    </Field>

    <!-- Slug | Edition Number -->
    <div class="grid grid-cols-2 gap-x-2 gap-y-6">
      <Field :data-invalid="!!errors?.slug">
        <FieldLabel for="slug">Slug</FieldLabel>
        <Input id="slug" v-model="form.slug" type="text" :aria-invalid="!!errors?.slug" />
        <FieldError :errors="errors.slug" />
      </Field>
      <Field :data-invalid="!!errors?.edition_number">
        <FieldLabel for="edition_number">Edition Number</FieldLabel>
        <InputNumber id="edition_number" v-model="form.edition_number" :min="1" :aria-invalid="!!errors?.edition_number" />
        <FieldError :errors="errors.edition_number" />
      </Field>
    </div>

    <!-- Start Date & Time | End Date & Time -->
    <div class="grid grid-cols-2 gap-x-2 gap-y-6">
      <Field :data-invalid="!!errors?.start_date">
        <FieldLabel for="start_date">Start Date & Time</FieldLabel>
        <DatePicker
          with-time
          v-model="form.start_date"
          placeholder="Select start date & time"
          :default-hour="10"
        />
        <FieldError :errors="errors.start_date" />
      </Field>
      <Field :data-invalid="!!errors?.end_date">
        <FieldLabel for="end_date">End Date & Time</FieldLabel>
        <DatePicker
          with-time
          v-model="form.end_date"
          placeholder="Select end date & time"
          :default-hour="18"
        />
        <FieldError :errors="errors.end_date" />
      </Field>
    </div>

    <div class="grid grid-cols-1 gap-x-2 gap-y-6 lg:grid-cols-3">
      <Field :data-invalid="!!errors?.location">
        <FieldLabel for="location">Venue</FieldLabel>
        <Input id="location" v-model="form.location" type="text" :aria-invalid="!!errors?.location" />
        <FieldError :errors="errors.location" />
      </Field>
      <Field>
        <FieldLabel for="location_short">Venue Short</FieldLabel>
        <Input id="location_short" v-model="form.custom_fields.location_short" type="text" />
      </Field>
      <Field :data-invalid="!!errors?.location_link">
        <FieldLabel for="location_link">Location Link</FieldLabel>
        <Input id="location_link" v-model="form.location_link" type="url" :aria-invalid="!!errors?.location_link" />
        <FieldError :errors="errors.location_link" />
      </Field>
    </div>

    <div class="grid grid-cols-1 gap-x-2 gap-y-6 lg:grid-cols-2">
      <Field :data-invalid="!!errors?.hall">
        <FieldLabel for="hall">Hall</FieldLabel>
        <Input id="hall" v-model="form.hall" type="text" :aria-invalid="!!errors?.hall" />
        <FieldError :errors="errors.hall" />
      </Field>
      <!-- Saleable Area -->
      <Field :data-invalid="!!errors?.saleable_area">
        <FieldLabel for="saleable_area">Saleable Area (m²)</FieldLabel>
        <InputNumber
          :aria-invalid="!!errors?.saleable_area"
          id="saleable_area"
          v-model="form.saleable_area"
          :min="0"
          decimal
          placeholder="e.g. 5000"
        />
        <FieldError :errors="errors.saleable_area" />
      </Field>
    </div>

    <!-- Poster Image -->
    <Field :data-invalid="!!errors?.tmp_poster_image">
      <FieldLabel>Poster Image</FieldLabel>
      <p class="text-muted-foreground text-xs">1080 x 1350px, format JPG / PNG</p>
      <InputFileImage
        ref="posterImageInputRef"
        v-model="imageFiles.poster_image"
        :initial-image="initialData?.poster_image"
        v-model:delete-flag="deleteFlags.poster_image"
        container-class="relative isolate aspect-4/5 max-w-full"
      />
      <FieldError :errors="errors.tmp_poster_image" />
    </Field>

    <!-- Teaser Video ID -->
    <Field>
      <FieldLabel for="teaser_video_id">Teaser Video ID</FieldLabel>
      <Input id="teaser_video_id" v-model="form.custom_fields.teaser_video_id" type="text" />
      <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
        YouTube video ID for the teaser (e.g. "1Tsjh4xvtFw").
      </p>
    </Field>

    <!-- Visitor E-guide -->
    <Field class="min-w-0" :data-invalid="!!errors?.tmp_visitor_eguide">
      <FieldLabel>Visitor E-guide</FieldLabel>
      <p class="text-muted-foreground text-xs">PDF file, max 20MB</p>
      <AttachmentLink
        v-if="initialData?.visitor_eguide && !eguideFiles.length && !deleteFlags.visitor_eguide"
        :file="initialData.visitor_eguide"
      >
        <template #actions>
          <ButtonCopy :text="initialData.visitor_eguide.url" />
          <AttachmentAction
            v-tippy="'Remove file'"
            type="button"
            aria-label="Remove file"
            @click="deleteFlags.visitor_eguide = true"
          >
            <Icon name="hugeicons:delete-01" class="size-4" />
          </AttachmentAction>
        </template>
      </AttachmentLink>
      <InputFile
        :aria-invalid="!!errors?.tmp_visitor_eguide"
        v-else
        ref="visitorEguideInputRef"
        v-model="eguideFiles"
        :accepted-file-types="['application/pdf']"
        max-file-size="20MB"
      />
      <button
        v-if="deleteFlags.visitor_eguide && initialData?.visitor_eguide"
        type="button"
        class="text-muted-foreground hover:text-foreground text-xs font-medium tracking-tight"
        @click="deleteFlags.visitor_eguide = false"
      >
        Undo remove
      </button>
      <FieldError :errors="errors.tmp_visitor_eguide" />
    </Field>

    <!-- Description -->
    <Field :data-invalid="!!errors?.description">
      <FieldLabel for="description">Description</FieldLabel>
      <TipTapEditor
        v-model="form.description"
        model-type="App\Models\Event"
        collection="description_images"
        :sticky="false"
        min-height="200px"
        placeholder="Write event description"
      />
      <FieldError :errors="errors.description" />
    </Field>

    <!-- Status | Visibility -->
    <div class="grid grid-cols-2 gap-x-2 gap-y-6">
      <Field :data-invalid="!!errors?.status">
        <FieldLabel for="status">Status</FieldLabel>
        <Select v-model="form.status">
          <SelectTrigger class="w-full" :aria-invalid="!!errors?.status">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="draft">Draft</SelectItem>
            <SelectItem value="published">Published</SelectItem>
            <SelectItem value="archived">Archived</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
          </SelectContent>
        </Select>
        <FieldError :errors="errors.status" />
      </Field>
      <Field :data-invalid="!!errors?.visibility">
        <FieldLabel for="visibility">Visibility</FieldLabel>
        <Select v-model="form.visibility">
          <SelectTrigger class="w-full" :aria-invalid="!!errors?.visibility">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="public">Public</SelectItem>
            <SelectItem value="private">Private</SelectItem>
          </SelectContent>
        </Select>
        <FieldError :errors="errors.visibility" />
      </Field>
    </div>

    <div class="flex justify-end">
      <Button type="submit" :disabled="loading">
        <Spinner v-if="loading" />
        {{ loading ? submitLoadingText : submitText }}
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>
  </form>
</template>

<script setup>
import { AttachmentAction } from "@/components/ui/attachment";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { toast } from "vue-sonner";

const { metaSymbol } = useShortcuts();

const FILE_STATUS = {
  PROCESSING: 3,
};

function createEmptyForm() {
  return {
    title: "",
    slug: "",
    edition_number: null,
    saleable_area: null,
    description: "",
    start_date: null,
    end_date: null,
    location: "",
    location_link: "",
    hall: "",
    status: "published",
    visibility: "public",
    custom_fields: {},
  };
}

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
  loading: {
    type: Boolean,
    default: false,
  },
  errors: {
    type: Object,
    default: () => ({}),
  },
  isCreate: {
    type: Boolean,
    default: false,
  },
  submitText: {
    type: String,
    default: "Save Event",
  },
  submitLoadingText: {
    type: String,
    default: "Saving..",
  },
});

const emit = defineEmits(["submit"]);

const deleteFlags = ref({
  poster_image: false,
  visitor_eguide: false,
});

const imageFiles = ref({
  poster_image: [],
});

const eguideFiles = ref([]);

const posterImageInputRef = ref(null);
const visitorEguideInputRef = ref(null);

const slugManuallyEdited = ref(false);

const form = reactive(createEmptyForm());

// Slug auto-generation from title (same pattern as PostEditor)
function slugify(text) {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, "")
    .replace(/[\s_-]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

watch(
  () => form.title,
  (newTitle) => {
    if (!slugManuallyEdited.value && newTitle) {
      form.slug = slugify(newTitle);
    }
  }
);

watch(
  () => form.slug,
  (newSlug, oldSlug) => {
    if (oldSlug !== undefined && newSlug !== slugify(form.title)) {
      slugManuallyEdited.value = true;
    }
  }
);

// Format Date to local datetime string for backend (YYYY-MM-DD HH:mm:ss)
function formatDateTimeForBackend(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day} ${hours}:${minutes}:00`;
}

function populateForm(data) {
  if (!data || Object.keys(data).length === 0) return;

  form.title = data.title || "";
  form.slug = data.slug || "";
  form.edition_number = data.edition_number || null;
  form.saleable_area = data.saleable_area || null;
  form.description = data.description || "";
  form.start_date = data.start_date ? new Date(data.start_date) : null;
  form.end_date = data.end_date ? new Date(data.end_date) : null;
  form.location = data.location || "";
  form.location_link = data.location_link || "";
  form.hall = data.hall || "";
  form.status = data.status || "published";
  form.visibility = data.visibility || "public";
  form.custom_fields = data.custom_fields || {};

  imageFiles.value.poster_image = [];
  deleteFlags.value.poster_image = false;
  eguideFiles.value = [];
  deleteFlags.value.visitor_eguide = false;

  // Mark slug as manually edited when loading existing data
  if (data.slug) {
    slugManuallyEdited.value = true;
  }
}

watch(
  () => props.initialData,
  (newData) => {
    populateForm(newData);
  },
  { immediate: true }
);

function hasFilesUploading() {
  return [posterImageInputRef, visitorEguideInputRef].some((ref) =>
    ref.value?.pond?.getFiles().some((file) => file.status === FILE_STATUS.PROCESSING)
  );
}

function handleSubmit() {
  if (hasFilesUploading()) {
    toast.error("Please wait until all files are uploaded");
    return;
  }

  const payload = {
    title: form.title,
    slug: form.slug || null,
    edition_number: form.edition_number || null,
    saleable_area: form.saleable_area || null,
    description: form.description || null,
    start_date: formatDateTimeForBackend(form.start_date),
    end_date: formatDateTimeForBackend(form.end_date),
    location: form.location || null,
    location_link: form.location_link || null,
    hall: form.hall || null,
    status: form.status,
    visibility: form.visibility,
    custom_fields: Object.keys(form.custom_fields).length > 0 ? form.custom_fields : null,
  };

  // Handle poster image
  const posterValue = imageFiles.value.poster_image?.[0];
  if (posterValue && posterValue.startsWith("tmp-")) {
    payload.tmp_poster_image = posterValue;
  } else if (deleteFlags.value.poster_image && !posterValue) {
    payload.delete_poster_image = true;
  }

  // Handle visitor e-guide PDF
  const eguideValue = eguideFiles.value?.[0];
  if (eguideValue && eguideValue.startsWith("tmp-")) {
    payload.tmp_visitor_eguide = eguideValue;
  } else if (deleteFlags.value.visitor_eguide) {
    payload.delete_visitor_eguide = true;
  }

  emit("submit", payload);
}

defineExpose({
  form,
  imageFiles,
  handleSubmit,
});

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
