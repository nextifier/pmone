<template>
  <div class="frame">
    <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
      <h3 class="text-base font-semibold tracking-tight">{{ label }}</h3>
    </div>

    <div class="frame-panel space-y-4">
      <div class="space-y-2">
        <Label :for="`og-image-${pageKey}`">OG Image</Label>
        <InputFileImage
          :id="`og-image-${pageKey}`"
          v-model="tmpFiles"
          v-model:delete-flag="deleteFlag"
          :initial-image="current.image"
          container-class="relative isolate aspect-[1200/630] w-full"
          image-class="border-border size-full rounded-lg border object-cover"
        />
        <p class="text-muted-foreground text-xs">
          Recommended: 1200x630px. Larger images are cropped automatically.
        </p>
      </div>

      <div class="space-y-2">
        <Label :for="`og-title-${pageKey}`">OG Title</Label>
        <Input
          :id="`og-title-${pageKey}`"
          v-model="form.title"
          placeholder="Falls back to the page title"
          maxlength="255"
        />
      </div>

      <div class="space-y-2">
        <Label :for="`og-description-${pageKey}`">OG Description</Label>
        <Textarea
          :id="`og-description-${pageKey}`"
          v-model="form.description"
          placeholder="Falls back to the page description"
          maxlength="500"
          rows="2"
        />
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
        <Button
          variant="outline"
          size="sm"
          :disabled="!websiteUrl || capture.processing.value"
          v-tippy="captureTooltip"
          @click="startCapture"
        >
          <Spinner v-if="capture.processing.value" />
          <Icon v-else name="hugeicons:camera-01" />
          <span>{{ captureLabel }}</span>
        </Button>

        <Button size="sm" :disabled="saving || !dirty" @click="save">
          <Spinner v-if="saving" />
          <span>Save</span>
        </Button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { toast } from "vue-sonner";

const props = defineProps({
  pageKey: { type: String, required: true },
  label: { type: String, required: true },
  path: { type: String, required: true },
  username: { type: String, required: true },
  websiteUrl: { type: String, default: null },
  initial: { type: Object, default: () => ({}) },
});

const client = useSanctumClient();

const current = reactive({
  title: props.initial?.title ?? null,
  description: props.initial?.description ?? null,
  image: props.initial?.image ?? null,
});

const form = reactive({
  title: current.title ?? "",
  description: current.description ?? "",
});

// The parent reloads all pages after a "capture all" batch; adopt the fresh
// data without clobbering text the user is still editing.
watch(
  () => props.initial,
  (val) => {
    const titleWasClean = form.title === (current.title ?? "");
    const descriptionWasClean = form.description === (current.description ?? "");

    current.title = val?.title ?? null;
    current.description = val?.description ?? null;
    current.image = val?.image ?? null;

    if (titleWasClean) form.title = current.title ?? "";
    if (descriptionWasClean) form.description = current.description ?? "";
  }
);

const tmpFiles = ref([]);
const deleteFlag = ref(false);
const saving = ref(false);

const hasTmpUpload = computed(
  () => typeof tmpFiles.value[0] === "string" && tmpFiles.value[0].startsWith("tmp-")
);

const dirty = computed(
  () =>
    form.title !== (current.title ?? "") ||
    form.description !== (current.description ?? "") ||
    hasTmpUpload.value ||
    (deleteFlag.value && !!current.image)
);

async function save() {
  saving.value = true;
  try {
    const body = {
      pages: {
        [props.pageKey]: {
          title: form.title || null,
          description: form.description || null,
        },
      },
    };

    if (hasTmpUpload.value) {
      body.tmp_images = { [props.pageKey]: tmpFiles.value[0] };
    } else if (deleteFlag.value && current.image) {
      body.delete_images = { [props.pageKey]: true };
    }

    const res = await client(`/api/projects/${props.username}/og-images`, {
      method: "PUT",
      body,
    });

    const updated = res?.pages?.[props.pageKey];
    current.title = updated?.title ?? null;
    current.description = updated?.description ?? null;
    current.image = updated?.image ?? null;
    tmpFiles.value = [];
    deleteFlag.value = false;

    toast.success(`${props.label} OG settings saved`);
  } catch (err) {
    toast.error("Failed to save OG settings", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
}

// Capture from the live website (queued Browsershot job on the backend)
const capture = useJobProgress();

const captureLabel = computed(() => {
  if (!capture.processing.value) return "Capture from website";
  return capture.progress.value?.message || "Capturing...";
});

const captureTooltip = computed(() =>
  props.websiteUrl
    ? `Screenshot ${props.websiteUrl.replace(/\/$/, "")}${props.path === "/" ? "" : props.path}`
    : "Add a Website link to the project first"
);

async function startCapture() {
  try {
    await capture.startJob(`/api/projects/${props.username}/og-images/${props.pageKey}/capture`);
  } catch (err) {
    capture.reset();
    toast.error("Failed to start capture", {
      description: err?.data?.message || err?.message,
    });
  }
}

watch(
  () => capture.progress.value?.status,
  (status) => {
    if (status === "completed") {
      const image = capture.progress.value?.image;
      if (image) {
        current.image = image;
        tmpFiles.value = [];
        deleteFlag.value = false;
      }
      toast.success(`${props.label} page captured`);
      capture.reset();
    } else if (status === "failed") {
      toast.error("Capture failed", {
        description: capture.progress.value?.error_message || "Please try again.",
      });
      capture.reset();
    }
  }
);
</script>
