<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="28rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">{{ mode === "create" ? "Shorten a Link" : "Edit Short Link" }}</h3>
        <p class="page-description">
          {{
            mode === "create" ? "Paste a long URL and get a short one." : "Update your short link."
          }}
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
        <!-- Destination URL -->
        <div class="space-y-2">
          <Label for="destination_url">Destination URL</Label>
          <Input
            id="destination_url"
            v-model="formData.destination_url"
            type="url"
            placeholder="Paste the URL you want to shorten"
            required
            auto-focus
          />
          <p v-if="errors.destination_url" class="text-destructive text-xs sm:text-sm">
            {{ errors.destination_url[0] }}
          </p>
        </div>

        <!-- Short Link Slug -->
        <div class="space-y-2">
          <Label for="slug">Short Link</Label>
          <InputGroup>
            <InputGroupAddon>
              <InputGroupText>{{ appDomain }}/</InputGroupText>
            </InputGroupAddon>
            <InputGroupInput id="slug" v-model="formData.slug" required />
            <InputGroupAddon align="inline-end">
              <!-- Slug availability indicator -->
              <Spinner v-if="slugChecking" class="size-4" />
              <Icon
                v-else-if="slugAvailable === true"
                name="lucide:check"
                class="text-success-foreground size-4"
              />
              <Icon
                v-else-if="slugAvailable === false"
                name="lucide:x"
                class="text-destructive size-4"
              />
            </InputGroupAddon>
          </InputGroup>
          <p v-if="errors.slug" class="text-destructive text-xs sm:text-sm">{{ errors.slug[0] }}</p>
          <p
            v-else-if="slugAvailable === false"
            class="text-destructive text-xs tracking-tight sm:text-sm"
          >
            This slug is already taken.
          </p>
          <p v-else class="text-muted-foreground text-xs tracking-tight">
            Letters, numbers, dots, underscores, and hyphens only.
          </p>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
          <Button type="submit" :disabled="loading || slugChecking || slugAvailable === false">
            <Spinner v-if="loading" />
            {{ mode === "create" ? "Create" : "Save" }}
            <KbdGroup>
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
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
  InputGroupText,
} from "@/components/ui/input-group";
import { toast } from "vue-sonner";

const props = defineProps({
  shortLink: { type: Object, default: null },
});

const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

const appDomain = useRuntimeConfig().public.siteUrl.replace(/^https?:\/\//, "");

const mode = computed(() => (props.shortLink ? "edit" : "create"));
const formData = ref({
  slug: "",
  destination_url: "",
});
const errors = ref({});
const loading = ref(false);

// Slug availability check
const slugChecking = ref(false);
const slugAvailable = ref(null);
let slugCheckTimeout = null;

function generateRandomSlug(length = 6) {
  const chars = "abcdefghijklmnopqrstuvwxyz0123456789";
  let result = "";
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

async function checkSlugAvailability(slug) {
  if (!slug) {
    slugAvailable.value = null;
    slugChecking.value = false;
    return;
  }

  try {
    slugChecking.value = true;
    const params = new URLSearchParams({ slug });
    if (props.shortLink?.id) {
      params.append("exclude_id", props.shortLink.id);
    }
    const response = await sanctumFetch(`/api/short-links/check-slug?${params.toString()}`);
    slugAvailable.value = response.available;
  } catch {
    slugAvailable.value = null;
  } finally {
    slugChecking.value = false;
  }
}

watch(
  () => formData.value.slug,
  (newSlug) => {
    slugAvailable.value = null;
    slugChecking.value = false;
    if (slugCheckTimeout) clearTimeout(slugCheckTimeout);

    if (!newSlug) return;

    slugChecking.value = true;
    slugCheckTimeout = setTimeout(() => {
      checkSlugAvailability(newSlug);
    }, 400);
  }
);

watch(isOpen, (val) => {
  if (val) {
    slugAvailable.value = null;
    slugChecking.value = false;
    if (slugCheckTimeout) clearTimeout(slugCheckTimeout);

    if (props.shortLink) {
      formData.value = {
        slug: props.shortLink.slug,
        destination_url: props.shortLink.destination_url,
      };
    } else {
      formData.value = {
        slug: generateRandomSlug(),
        destination_url: "",
      };
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
      mode.value === "create"
        ? "Short link created successfully!"
        : "Short link updated successfully!"
    );
    isOpen.value = false;
    emit("success");
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      const firstErrorField = Object.keys(err.response._data.errors)[0];
      toast.error(err.response._data.errors[firstErrorField][0]);
    } else {
      toast.error(
        err.response?._data?.message || err.message || `Failed to ${mode.value} short link`
      );
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
