<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="30rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">Edit Banner</h3>
        <p class="page-description">Update the banner image, link, and schedule.</p>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
        <!-- Banner Image -->
        <div class="space-y-2">
          <Label>Banner Image</Label>
          <InputFileImage
            ref="imageInputRef"
            v-model="imageFiles"
            :initial-image="props.banner?.image"
            v-model:delete-flag="deleteImage"
            container-class="relative isolate aspect-video max-w-full"
          />
          <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
            16:9 ratio. JPG, PNG, or WebP.
          </p>
          <p v-if="errors.tmp_image" class="text-destructive text-xs sm:text-sm">
            {{ errors.tmp_image[0] }}
          </p>
        </div>

        <!-- URL -->
        <div class="space-y-2">
          <Label for="banner-url">Destination URL (optional)</Label>
          <InputLink id="banner-url" v-model="formData.url" placeholder="https://example.com" />
          <p v-if="errors.url" class="text-destructive text-xs sm:text-sm">{{ errors.url[0] }}</p>
        </div>

        <!-- Caption -->
        <div class="space-y-2">
          <Label for="banner-caption">Caption (optional)</Label>
          <Input id="banner-caption" v-model="formData.caption" placeholder="Short banner caption" />
          <p v-if="errors.caption" class="text-destructive text-xs sm:text-sm">
            {{ errors.caption[0] }}
          </p>
        </div>

        <!-- Schedule -->
        <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
          <div class="space-y-2">
            <Label for="starts_at">Starts at (optional)</Label>
            <DatePicker
              v-model="formData.starts_at"
              with-time
              placeholder="Select date & time"
              :default-hour="0"
              :default-minute="0"
            />
            <p v-if="errors.starts_at" class="text-destructive text-xs sm:text-sm">
              {{ errors.starts_at[0] }}
            </p>
          </div>
          <div class="space-y-2">
            <Label for="ends_at">Ends at (optional)</Label>
            <DatePicker
              v-model="formData.ends_at"
              with-time
              placeholder="Select date & time"
              :default-hour="23"
              :default-minute="59"
            />
            <p v-if="errors.ends_at" class="text-destructive text-xs sm:text-sm">
              {{ errors.ends_at[0] }}
            </p>
          </div>
        </div>

        <!-- Active -->
        <div class="flex items-center justify-between gap-2">
          <div class="space-y-0.5">
            <Label for="banner-active">Active</Label>
            <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
              Inactive banners are hidden from the public page.
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
import { toast } from "vue-sonner";

const props = defineProps({
  linkPageSlug: { type: String, required: true },
  banner: { type: Object, default: null },
});

const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

const formData = ref({
  url: "",
  caption: "",
  is_active: true,
  starts_at: null,
  ends_at: null,
});
const errors = ref({});
const loading = ref(false);

// Image upload state
const imageFiles = ref([]);
const deleteImage = ref(false);
const imageInputRef = ref(null);

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
  if (val && props.banner) {
    formData.value = {
      url: props.banner.url || "",
      caption: props.banner.caption || "",
      is_active: props.banner.is_active ?? true,
      starts_at: props.banner.starts_at ? new Date(props.banner.starts_at) : null,
      ends_at: props.banner.ends_at ? new Date(props.banner.ends_at) : null,
    };
    errors.value = {};
    imageFiles.value = [];
    deleteImage.value = false;
  }
});

async function handleSubmit() {
  // Wait for any in-flight image upload to settle.
  const pondInstance = imageInputRef.value?.pond;
  if (pondInstance?.getFiles().some((f) => f.status !== 5 && f.status !== 2)) {
    toast.error("Please wait for the image to finish uploading");
    return;
  }

  loading.value = true;
  errors.value = {};

  try {
    const body = {
      url: formData.value.url || null,
      caption: formData.value.caption || null,
      is_active: formData.value.is_active,
      starts_at: formatDateTimeForBackend(formData.value.starts_at),
      ends_at: formatDateTimeForBackend(formData.value.ends_at),
    };

    if (imageFiles.value.length > 0 && imageFiles.value[0]) {
      body.tmp_image = imageFiles.value[0];
    }

    if (deleteImage.value) {
      body.delete_image = true;
    }

    await sanctumFetch(`/api/link-pages/${props.linkPageSlug}/banners/${props.banner.id}`, {
      method: "PUT",
      body,
    });
    toast.success("Banner updated!");
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
