<template>
  <form @submit.prevent="handleSubmit" class="grid gap-y-6">
    <!-- Event Title -->
    <div class="space-y-2">
      <Label for="title">Event Title</Label>
      <Input id="title" v-model="form.title" type="text" required />
      <InputErrorMessage :errors="errors.title" />
    </div>

    <!-- Slug | Edition Number -->
    <div class="grid grid-cols-2 gap-x-2 gap-y-6">
      <div class="space-y-2">
        <Label for="slug">Slug</Label>
        <Input id="slug" v-model="form.slug" type="text" />
        <InputErrorMessage :errors="errors.slug" />
      </div>
      <div class="space-y-2">
        <Label for="edition_number">Edition Number</Label>
        <Input id="edition_number" v-model.number="form.edition_number" type="number" min="1" />
        <InputErrorMessage :errors="errors.edition_number" />
      </div>
    </div>

    <!-- Start Date & Time | End Date & Time -->
    <div class="grid grid-cols-2 gap-x-2 gap-y-6">
      <div class="space-y-2">
        <Label for="start_date">Start Date & Time</Label>
        <DateTimePicker
          v-model="form.start_date"
          placeholder="Select start date & time"
          :default-hour="10"
        />
        <InputErrorMessage :errors="errors.start_date" />
      </div>
      <div class="space-y-2">
        <Label for="end_date">End Date & Time</Label>
        <DateTimePicker
          v-model="form.end_date"
          placeholder="Select end date & time"
          :default-hour="18"
        />
        <InputErrorMessage :errors="errors.end_date" />
      </div>
    </div>

    <!-- Saleable Area -->
    <div class="space-y-2">
      <Label for="saleable_area">Saleable Area (m²)</Label>
      <Input id="saleable_area" v-model.number="form.saleable_area" type="number" min="0" step="0.01" placeholder="e.g. 5000" />
      <InputErrorMessage :errors="errors.saleable_area" />
    </div>

    <!-- Venue | Hall | Location Link -->
    <div class="grid grid-cols-1 gap-x-2 gap-y-6 lg:grid-cols-3">
      <div class="space-y-2">
        <Label for="location">Venue</Label>
        <Input id="location" v-model="form.location" type="text" />
        <InputErrorMessage :errors="errors.location" />
      </div>
      <div class="space-y-2">
        <Label for="hall">Hall</Label>
        <Input id="hall" v-model="form.hall" type="text" />
        <InputErrorMessage :errors="errors.hall" />
      </div>
      <div class="space-y-2">
        <Label for="location_link">Location Link</Label>
        <Input id="location_link" v-model="form.location_link" type="url" />
        <InputErrorMessage :errors="errors.location_link" />
      </div>
    </div>

    <!-- Poster Image -->
    <div class="space-y-2">
      <div class="space-y-1">
        <Label>Poster Image</Label>
        <p class="text-muted-foreground text-xs">1080 x 1350px, format JPG / PNG</p>
      </div>
      <InputFileImage
        ref="posterImageInputRef"
        v-model="imageFiles.poster_image"
        :initial-image="initialData?.poster_image"
        v-model:delete-flag="deleteFlags.poster_image"
        container-class="relative isolate aspect-4/5 max-w-full"
      />
      <InputErrorMessage :errors="errors.tmp_poster_image" />
    </div>

    <!-- Description -->
    <div class="space-y-2">
      <Label for="description">Description</Label>
      <TipTapEditor
        v-model="form.description"
        model-type="App\Models\Event"
        collection="description_images"
        :sticky="false"
        min-height="200px"
        placeholder="Write event description..."
      />
      <InputErrorMessage :errors="errors.description" />
    </div>

    <!-- Status | Visibility -->
    <div class="grid grid-cols-2 gap-x-2 gap-y-6">
      <div class="space-y-2">
        <Label for="status">Status</Label>
        <Select v-model="form.status">
          <SelectTrigger class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="draft">Draft</SelectItem>
            <SelectItem value="published">Published</SelectItem>
            <SelectItem value="archived">Archived</SelectItem>
            <SelectItem value="cancelled">Cancelled</SelectItem>
          </SelectContent>
        </Select>
        <InputErrorMessage :errors="errors.status" />
      </div>
      <div class="space-y-2">
        <Label for="visibility">Visibility</Label>
        <Select v-model="form.visibility">
          <SelectTrigger class="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="public">Public</SelectItem>
            <SelectItem value="private">Private</SelectItem>
          </SelectContent>
        </Select>
        <InputErrorMessage :errors="errors.visibility" />
      </div>
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
import DateTimePicker from "@/components/DateTimePicker.vue";
import TipTapEditor from "@/components/TipTapEditor.vue";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
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
    status: "draft",
    visibility: "private",
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
});

const imageFiles = ref({
  poster_image: [],
});

const posterImageInputRef = ref(null);
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
  form.status = data.status || "draft";
  form.visibility = data.visibility || "private";
  form.custom_fields = data.custom_fields || {};

  imageFiles.value.poster_image = [];
  deleteFlags.value.poster_image = false;

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
  return [posterImageInputRef].some((ref) =>
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
