<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="28rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">{{ mode === "create" ? "Add Item" : "Edit Item" }}</h3>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
        <div class="space-y-2">
          <Label for="label">Label</Label>
          <Input id="label" v-model="formData.label" placeholder="My Website" required auto-focus />
          <p v-if="errors.label" class="text-destructive text-xs sm:text-sm">{{ errors.label[0] }}</p>
        </div>

        <div class="space-y-2">
          <Label for="url">URL</Label>
          <Input id="url" v-model="formData.url" type="url" placeholder="https://example.com" required />
          <p v-if="errors.url" class="text-destructive text-xs sm:text-sm">{{ errors.url[0] }}</p>
        </div>

        <div class="space-y-2">
          <Label for="item-description">Description</Label>
          <Textarea
            id="item-description"
            v-model="formData.description"
            placeholder="Optional description"
            rows="2"
          />
        </div>

        <!-- Poster Image -->
        <div class="space-y-2">
          <Label>Poster Image</Label>
          <InputFileImage
            ref="posterInputRef"
            v-model="imageFiles"
            :initial-image="props.item?.poster"
            v-model:delete-flag="deletePoster"
            container-class="relative isolate max-w-full"
          />
          <p v-if="errors.tmp_poster" class="text-destructive text-xs sm:text-sm">
            {{ errors.tmp_poster[0] }}
          </p>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
          <Button type="submit" :disabled="loading">
            <Spinner v-if="loading" />
            {{ mode === "create" ? "Add" : "Save" }}
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
  item: { type: Object, default: null },
});

const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

const mode = computed(() => (props.item ? "edit" : "create"));
const formData = ref({ label: "", url: "", description: "" });
const errors = ref({});
const loading = ref(false);

// Image upload state
const imageFiles = ref([]);
const deletePoster = ref(false);
const posterInputRef = ref(null);

watch(isOpen, (val) => {
  if (val) {
    if (props.item) {
      formData.value = {
        label: props.item.label,
        url: props.item.url,
        description: props.item.description || "",
      };
    } else {
      formData.value = { label: "", url: "", description: "" };
    }
    errors.value = {};
    imageFiles.value = [];
    deletePoster.value = false;
  }
});

async function handleSubmit() {
  // Check if file is still uploading
  const pondInstance = posterInputRef.value?.pond;
  if (pondInstance?.getFiles().some((f) => f.status !== 5 && f.status !== 2)) {
    toast.error("Please wait for the image to finish uploading");
    return;
  }

  loading.value = true;
  errors.value = {};

  try {
    const endpoint =
      mode.value === "create"
        ? `/api/link-pages/${props.linkPageSlug}/items`
        : `/api/link-pages/${props.linkPageSlug}/items/${props.item.id}`;
    const method = mode.value === "create" ? "POST" : "PUT";

    const body = { ...formData.value };

    // Add temp poster if uploaded
    if (imageFiles.value.length > 0 && imageFiles.value[0]) {
      body.tmp_poster = imageFiles.value[0];
    }

    // Add delete flag if poster was removed
    if (deletePoster.value) {
      body.delete_poster = true;
    }

    await sanctumFetch(endpoint, { method, body });
    toast.success(mode.value === "create" ? "Item added!" : "Item updated!");
    isOpen.value = false;
    emit("success");
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      toast.error(Object.values(err.response._data.errors)[0][0]);
    } else {
      toast.error(err.response?._data?.message || err.message || "Failed to save item");
    }
  } finally {
    loading.value = false;
  }
}

defineShortcuts({
  meta_s: { usingInput: true, handler: () => { if (isOpen.value) handleSubmit(); } },
});
</script>
