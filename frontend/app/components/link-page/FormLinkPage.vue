<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="28rem">
    <div class="px-4 pb-10 md:px-6 md:py-5">
      <div class="space-y-1">
        <h3 class="page-title">{{ mode === "create" ? "Create Link Page" : "Edit Link Page" }}</h3>
        <p class="page-description">
          {{ mode === "create" ? "Create a page with multiple links." : "Update your link page." }}
        </p>
      </div>

      <form @submit.prevent="handleSubmit" class="mt-4 space-y-4">
        <div class="space-y-2">
          <Label for="title">Title</Label>
          <Input id="title" v-model="formData.title" placeholder="My Link Page" required auto-focus />
          <p v-if="errors.title" class="text-destructive text-xs sm:text-sm">{{ errors.title[0] }}</p>
        </div>

        <div class="space-y-2">
          <Label for="slug">Slug</Label>
          <InputGroup>
            <InputGroupAddon>
              <InputGroupText>{{ appDomain }}/</InputGroupText>
            </InputGroupAddon>
            <InputGroupInput id="slug" v-model="formData.slug" required />
            <InputGroupAddon align="inline-end">
              <Spinner v-if="slugChecking" class="size-4" />
              <Icon v-else-if="slugAvailable === true" name="lucide:check" class="text-success-foreground size-4" />
              <Icon v-else-if="slugAvailable === false" name="lucide:x" class="text-destructive size-4" />
            </InputGroupAddon>
          </InputGroup>
          <p v-if="errors.slug" class="text-destructive text-xs sm:text-sm">{{ errors.slug[0] }}</p>
          <p v-else-if="slugAvailable === false" class="text-destructive text-xs sm:text-sm tracking-tight">This slug is already taken.</p>
          <p v-else class="text-muted-foreground text-xs tracking-tight">Letters, numbers, dots, underscores, and hyphens only.</p>
        </div>

        <div class="space-y-2">
          <Label for="description">Description</Label>
          <Textarea id="description" v-model="formData.description" placeholder="Optional description" rows="3" />
          <p v-if="errors.description" class="text-destructive text-xs sm:text-sm">{{ errors.description[0] }}</p>
        </div>

        <div class="space-y-2">
          <Label for="visibility">Visibility</Label>
          <select id="visibility" v-model="formData.visibility" class="border-border bg-background w-full rounded-md border px-3 py-2 text-sm tracking-tight">
            <option value="public">Public</option>
            <option value="unlisted">Unlisted</option>
          </select>
        </div>

        <div class="flex justify-end gap-2">
          <Button variant="outline" type="button" @click="isOpen = false">Cancel</Button>
          <Button type="submit" :disabled="loading || slugChecking || slugAvailable === false">
            <Spinner v-if="loading" />
            {{ mode === "create" ? "Create" : "Save" }}
            <KbdGroup><Kbd>{{ metaSymbol }}</Kbd><Kbd>S</Kbd></KbdGroup>
          </Button>
        </div>
      </form>
    </div>
  </DialogResponsive>
</template>

<script setup>
import DialogResponsive from "@/components/DialogResponsive.vue";
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from "@/components/ui/input-group";
import { toast } from "vue-sonner";

const props = defineProps({
  linkPage: { type: Object, default: null },
});

const emit = defineEmits(["success"]);
const isOpen = defineModel("open", { type: Boolean, default: false });

const sanctumFetch = useSanctumClient();
const { metaSymbol } = useShortcuts();

const appDomain = useRuntimeConfig().public.siteUrl.replace(/^https?:\/\//, "");

const mode = computed(() => (props.linkPage ? "edit" : "create"));
const formData = ref({ title: "", slug: "", description: "", visibility: "public" });
const errors = ref({});
const loading = ref(false);

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
  if (!slug) { slugAvailable.value = null; slugChecking.value = false; return; }
  try {
    slugChecking.value = true;
    const params = new URLSearchParams({ slug });
    if (props.linkPage?.id) params.append("exclude_id", props.linkPage.id);
    const response = await sanctumFetch(`/api/link-pages/check-slug?${params.toString()}`);
    slugAvailable.value = response.available;
  } catch { slugAvailable.value = null; } finally { slugChecking.value = false; }
}

watch(() => formData.value.slug, (newSlug) => {
  slugAvailable.value = null; slugChecking.value = false;
  if (slugCheckTimeout) clearTimeout(slugCheckTimeout);
  if (!newSlug) return;
  slugChecking.value = true;
  slugCheckTimeout = setTimeout(() => checkSlugAvailability(newSlug), 400);
});

watch(isOpen, (val) => {
  if (val) {
    slugAvailable.value = null; slugChecking.value = false;
    if (slugCheckTimeout) clearTimeout(slugCheckTimeout);
    if (props.linkPage) {
      formData.value = {
        title: props.linkPage.title,
        slug: props.linkPage.slug,
        description: props.linkPage.description || "",
        visibility: props.linkPage.visibility || "public",
      };
    } else {
      formData.value = { title: "", slug: generateRandomSlug(), description: "", visibility: "public" };
    }
    errors.value = {};
  }
});

async function handleSubmit() {
  loading.value = true; errors.value = {};
  try {
    const endpoint = mode.value === "create" ? "/api/link-pages" : `/api/link-pages/${props.linkPage.slug}`;
    const method = mode.value === "create" ? "POST" : "PUT";
    await sanctumFetch(endpoint, { method, body: formData.value });
    toast.success(mode.value === "create" ? "Link page created!" : "Link page updated!");
    isOpen.value = false;
    emit("success");
  } catch (err) {
    if (err.response?.status === 422 && err.response?._data?.errors) {
      errors.value = err.response._data.errors;
      toast.error(Object.values(err.response._data.errors)[0][0]);
    } else {
      toast.error(err.response?._data?.message || err.message || `Failed to ${mode.value} link page`);
    }
  } finally { loading.value = false; }
}

defineShortcuts({ meta_s: { usingInput: true, handler: () => { if (isOpen.value) handleSubmit(); } } });
</script>
