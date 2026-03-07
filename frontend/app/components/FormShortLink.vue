<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="28rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">{{ mode === "create" ? "Create Short Link" : "Edit Short Link" }}</h3>
        <p class="page-description">
          {{ mode === "create" ? "Add a new short link." : "Update the short link." }}
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
        <div class="space-y-2">
          <Label for="slug">Slug</Label>
          <Input
            id="slug"
            v-model="formData.slug"
            placeholder="my-link"
            required
            auto-focus
          />
          <p v-if="errors.slug" class="text-destructive text-xs">{{ errors.slug[0] }}</p>
          <p class="text-muted-foreground text-xs tracking-tight">
            The short URL slug (letters, numbers, dots, underscores, hyphens)
          </p>
        </div>

        <div class="space-y-2">
          <Label for="destination_url">Destination URL</Label>
          <Input
            id="destination_url"
            v-model="formData.destination_url"
            type="url"
            placeholder="https://example.com"
            required
          />
          <p v-if="errors.destination_url" class="text-destructive text-xs">
            {{ errors.destination_url[0] }}
          </p>
          <p class="text-muted-foreground text-xs tracking-tight">
            The full URL where the short link will redirect
          </p>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
          <Button type="submit" :disabled="loading">
            <Spinner v-if="loading" />
            {{ mode === "create" ? "Create" : "Save" }}
            <KbdGroup class="ml-1">
              <Kbd>{{ metaSymbol }}</Kbd>
              <Kbd>S</Kbd>
            </KbdGroup>
          </Button>
        </div>
      </form>
    </div>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { toast } from "vue-sonner";

const props = defineProps({
  shortLink: { type: Object, default: null },
});

const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

const mode = computed(() => (props.shortLink ? "edit" : "create"));
const formData = ref({
  slug: "",
  destination_url: "",
});
const errors = ref({});
const loading = ref(false);

watch(isOpen, (val) => {
  if (val) {
    if (props.shortLink) {
      formData.value = {
        slug: props.shortLink.slug,
        destination_url: props.shortLink.destination_url,
      };
    } else {
      formData.value = { slug: "", destination_url: "", is_active: true };
    }
    errors.value = {};
  }
});

async function handleSubmit() {
  loading.value = true;
  errors.value = {};

  try {
    const endpoint =
      mode.value === "create" ? "/api/short-links" : `/api/short-links/${props.shortLink.slug}`;
    const method = mode.value === "create" ? "POST" : "PUT";

    await sanctumFetch(endpoint, { method, body: formData.value });

    toast.success(
      mode.value === "create" ? "Short link created successfully!" : "Short link updated successfully!"
    );
    isOpen.value = false;
    emit("success");
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      toast.error(err.response._data.errors[firstErrorField][0]);
    } else {
      toast.error(err.response?._data?.message || err.message || `Failed to ${mode.value} short link`);
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
