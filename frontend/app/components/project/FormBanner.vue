<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="32rem" :overflow-content="true">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">{{ banner ? "Edit Banner" : "Add Banner" }}</h3>
        <p class="page-description">
          Banners appear in the hero carousel on the event website.
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
        <!-- Placement -->
        <div class="space-y-2">
          <Label for="banner-placement">Placement</Label>
          <Select v-model="formData.placement">
            <SelectTrigger id="banner-placement" class="w-full">
              <SelectValue placeholder="Select placement" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="p in placementOptions" :key="p.value" :value="p.value">
                {{ p.label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            Where this banner appears on the event website.
          </p>
          <p v-if="errors.placement" class="text-destructive text-xs sm:text-sm">
            {{ errors.placement[0] }}
          </p>
        </div>

        <!-- Type -->
        <div class="space-y-2">
          <Label for="banner-type">Type</Label>
          <Select v-model="formData.type">
            <SelectTrigger id="banner-type" class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="image">Image only</SelectItem>
              <SelectItem value="text">Text only</SelectItem>
              <SelectItem value="image_text">Image + Text</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Image -->
        <div v-if="showImage" class="space-y-2">
          <Label>Banner Image</Label>
          <InputFileImage
            ref="imageInputRef"
            v-model="imageFiles"
            :initial-image="banner?.image"
            v-model:delete-flag="deleteImage"
            :container-class="aspectContainerClass"
          />
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            JPG, PNG, or WebP. Recommended ratio: {{ formData.aspect_ratio }}.
          </p>
          <p v-if="errors.tmp_image" class="text-destructive text-xs sm:text-sm">
            {{ errors.tmp_image[0] }}
          </p>
        </div>

        <!-- Aspect ratio -->
        <div v-if="showImage" class="space-y-2">
          <Label for="banner-aspect">Aspect ratio</Label>
          <Select v-model="formData.aspect_ratio">
            <SelectTrigger id="banner-aspect" class="w-full">
              <SelectValue placeholder="Select ratio" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="r in aspectRatios" :key="r" :value="r">{{ r }}</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.aspect_ratio" class="text-destructive text-xs sm:text-sm">
            {{ errors.aspect_ratio[0] }}
          </p>
        </div>

        <!-- Caption (image lightbox) -->
        <div v-if="showImage" class="space-y-2">
          <Label for="banner-caption">Caption (optional)</Label>
          <Input
            id="banner-caption"
            v-model="formData.caption"
            placeholder="Shown under the image in the lightbox"
          />
        </div>

        <!-- Title (all types; alt text for image) -->
        <div class="space-y-2">
          <Label for="banner-title">{{ showText ? "Title" : "Title / alt text" }}</Label>
          <Input
            id="banner-title"
            v-model="formData.title"
            :placeholder="showText ? 'Headline' : 'Used as image alt text'"
          />
          <p v-if="errors.title" class="text-destructive text-xs sm:text-sm">{{ errors.title[0] }}</p>
        </div>

        <!-- Description (text types) -->
        <div v-if="showText" class="space-y-2">
          <Label>Description</Label>
          <TipTapEditor
            v-model="formData.description"
            model-type="App\Models\ProjectBanner"
            collection="description_images"
            :sticky="false"
            min-height="160px"
            placeholder="Supporting text"
          />
          <p v-if="errors.description" class="text-destructive text-xs sm:text-sm">
            {{ errors.description[0] }}
          </p>
        </div>

        <!-- CTA label (text types) -->
        <div v-if="showText" class="space-y-2">
          <Label for="banner-cta">CTA label (optional)</Label>
          <Input id="banner-cta" v-model="formData.cta_label" placeholder="e.g. Book Your Space Now" />
          <p v-if="errors.cta_label" class="text-destructive text-xs sm:text-sm">
            {{ errors.cta_label[0] }}
          </p>
        </div>

        <!-- Link (all types) -->
        <div class="space-y-2">
          <Label for="banner-link">Link (optional)</Label>
          <Input id="banner-link" v-model="formData.link" placeholder="https://example.com or /book-space" />
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            {{
              showText
                ? "Where the CTA / banner points to."
                : "Optional. Adds an external link button to the image."
            }}
          </p>
          <p v-if="errors.link" class="text-destructive text-xs sm:text-sm">{{ errors.link[0] }}</p>
        </div>

        <!-- Schedule -->
        <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="banner-start">Starts at (optional)</Label>
            <DatePicker
              v-model="formData.start_time"
              with-time
              placeholder="Select date & time"
              :default-hour="0"
              :default-minute="0"
            />
            <p v-if="errors.start_time" class="text-destructive text-xs sm:text-sm">
              {{ errors.start_time[0] }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="banner-end">Ends at (optional)</Label>
            <DatePicker
              v-model="formData.end_time"
              with-time
              placeholder="Select date & time"
              :default-hour="23"
              :default-minute="59"
            />
            <p v-if="errors.end_time" class="text-destructive text-xs sm:text-sm">
              {{ errors.end_time[0] }}
            </p>
          </div>
        </div>

        <!-- Active -->
        <div class="flex items-center justify-between gap-2">
          <div class="space-y-0.5">
            <Label for="banner-active">Active</Label>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Inactive banners are hidden from the website.
            </p>
          </div>
          <Switch id="banner-active" v-model="formData.is_active" />
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
          <Button type="submit" :disabled="loading">
            <Spinner v-if="loading" />
            Save
            <KbdGroup><Kbd>{{ metaSymbol }}</Kbd><Kbd>S</Kbd></KbdGroup>
          </Button>
        </div>
      </form>
    </div>
  </DialogResponsive>
</template>

<script setup>
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { toast } from "vue-sonner";

const props = defineProps({
  projectUsername: { type: String, required: true },
  banner: { type: Object, default: null },
});

const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

const aspectRatios = ["1:1", "16:9", "9:16", "4:5", "2:1", "4:1"];

const placementOptions = [
  { value: "hero", label: "Hero" },
  { value: "visitor-cta", label: "Visitor CTA (cross-promo)" },
  { value: "hero-announcement", label: "Hero Announcement" },
];

const defaultForm = () => ({
  placement: "hero",
  type: "image",
  title: "",
  description: "",
  cta_label: "",
  link: "",
  caption: "",
  aspect_ratio: "4:1",
  is_active: true,
  start_time: null,
  end_time: null,
});

const formData = ref(defaultForm());
const errors = ref({});
const loading = ref(false);

const imageFiles = ref([]);
const deleteImage = ref(false);
const imageInputRef = ref(null);

const showImage = computed(() => formData.value.type !== "text");
const showText = computed(() => formData.value.type !== "image");

const aspectClassMap = {
  "1:1": "aspect-square",
  "16:9": "aspect-video",
  "9:16": "aspect-[9/16]",
  "4:5": "aspect-[4/5]",
  "2:1": "aspect-[2/1]",
  "4:1": "aspect-[1920/480]",
};
const aspectContainerClass = computed(
  () => `relative isolate w-full ${aspectClassMap[formData.value.aspect_ratio] ?? "aspect-[1920/480]"}`,
);

function formatDateTimeForBackend(date) {
  if (!date) return null;
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day} ${hours}:${minutes}:00`;
}

watch(isOpen, (val) => {
  if (!val) return;
  errors.value = {};
  imageFiles.value = [];
  deleteImage.value = false;

  if (props.banner) {
    formData.value = {
      placement: props.banner.placement || "hero",
      type: props.banner.type || "image",
      title: props.banner.title || "",
      description: props.banner.description || "",
      cta_label: props.banner.cta_label || "",
      link: props.banner.link || "",
      caption: props.banner.settings?.caption || "",
      aspect_ratio: props.banner.aspect_ratio || "4:1",
      is_active: props.banner.is_active ?? true,
      start_time: props.banner.start_time ? new Date(props.banner.start_time) : null,
      end_time: props.banner.end_time ? new Date(props.banner.end_time) : null,
    };
  } else {
    formData.value = defaultForm();
  }
});

async function handleSubmit() {
  const pondInstance = imageInputRef.value?.pond;
  if (pondInstance?.getFiles().some((f) => f.status !== 5 && f.status !== 2)) {
    toast.error("Please wait for the image to finish uploading");
    return;
  }

  loading.value = true;
  errors.value = {};

  try {
    // TipTapEditor emits "<p></p>" for empty content — normalize to null.
    const rawDescription = formData.value.description?.trim();
    const description =
      showText.value && rawDescription && rawDescription !== "<p></p>" ? rawDescription : null;

    const settings = { ...(props.banner?.settings || {}) };
    if (showImage.value) {
      settings.caption = formData.value.caption || null;
    }

    const body = {
      type: formData.value.type,
      placement: formData.value.placement || "hero",
      title: formData.value.title || null,
      description,
      cta_label: showText.value ? formData.value.cta_label || null : null,
      link: formData.value.link || null,
      aspect_ratio: showImage.value ? formData.value.aspect_ratio : null,
      is_active: formData.value.is_active,
      start_time: formatDateTimeForBackend(formData.value.start_time),
      end_time: formatDateTimeForBackend(formData.value.end_time),
      settings: Object.keys(settings).length ? settings : null,
    };

    if (imageFiles.value.length > 0 && imageFiles.value[0]) {
      body.tmp_image = imageFiles.value[0];
    }
    if (deleteImage.value) {
      body.delete_image = true;
    }

    const base = `/api/projects/${props.projectUsername}/banners`;
    if (props.banner) {
      await sanctumFetch(`${base}/${props.banner.id}`, { method: "PUT", body });
      toast.success("Banner updated!");
    } else {
      await sanctumFetch(base, { method: "POST", body });
      toast.success("Banner created!");
    }

    isOpen.value = false;
    emit("success");
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      toast.error(Object.values(err.response._data.errors)[0][0]);
    } else {
      toast.error(err.response?._data?.message || err.message || "Failed to save banner");
    }
  } finally {
    loading.value = false;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      if (isOpen.value) handleSubmit();
    },
  },
});
</script>
