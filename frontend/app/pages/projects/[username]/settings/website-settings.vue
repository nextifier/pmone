<template>
  <div class="flex flex-col gap-y-6">
    <div class="space-y-1">
      <h2 class="page-title">Website Settings</h2>
      <p class="page-description">
        Control how the public website renders sections sourced from this project. Changes are saved
        automatically.
      </p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <div v-else class="flex flex-col gap-y-4">
      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:time-schedule" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Rundown</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Configure how the Rundown section behaves on the public event website.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="rundown-show-on-home"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Display Rundown section in the Home page
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                When on, the home page of the public event website includes the Rundown section.
              </p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                For private testing, add
                <code class="bg-muted text-foreground rounded-md px-1.5 py-0.5 font-mono"
                  >?show-rundown=true</code
                >
                to the home page URL to force-show this section even while this toggle is off.
              </p>
            </div>
            <Switch id="rundown-show-on-home" v-model="form.show_rundown_on_home_page" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="rundown-show-search"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show search bar on Rundown section
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Visitors can filter rundown items by typing a keyword.
              </p>
            </div>
            <Switch id="rundown-show-search" v-model="form.show_search_bar" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="rundown-show-location-filter"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show location filter on Rundown section
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Visitors can filter rundown items by venue or room.
              </p>
            </div>
            <Switch id="rundown-show-location-filter" v-model="form.show_location_filter" />
          </div>

          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="rundown-show-all-details"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show all rundown details without click
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                Render description, speakers, and panelists inline instead of behind a click.
              </p>
            </div>
            <Switch id="rundown-show-all-details" v-model="form.show_all_rundown_details" />
          </div>
        </div>
      </div>

      <div class="frame">
        <div class="flex items-start gap-x-2.5 px-3 py-3 lg:px-5">
          <Icon name="hugeicons:store-04" class="mt-0.5 size-5 shrink-0" />
          <div class="min-w-0 space-y-1">
            <h3 class="text-base font-semibold tracking-tight">Brands</h3>
            <p class="text-muted-foreground text-sm tracking-tight">
              Configure how the Brands section behaves on the public event website.
            </p>
          </div>
        </div>

        <div class="frame-panel divide-border divide-y !px-0 !py-0">
          <div class="flex items-start justify-between gap-4 px-4 py-5 lg:px-6">
            <div class="space-y-1">
              <Label
                for="brands-show-preview-on-home"
                class="cursor-pointer text-sm font-medium tracking-tight"
              >
                Show Brand Preview section in the Home page
              </Label>
              <p class="text-muted-foreground text-sm tracking-tight">
                When on, the home page of the public event website includes a Brand Preview
                carousel.
              </p>
              <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                For private testing, add
                <code class="bg-muted text-foreground rounded-md px-1.5 py-0.5 font-mono"
                  >?show-brands=true</code
                >
                to the home page URL to force-show this section even while this toggle is off.
              </p>
            </div>
            <Switch
              id="brands-show-preview-on-home"
              v-model="form.show_brand_preview_on_home_page"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

usePageMeta(null, {
  title: computed(() => `Website Settings · ${props.project?.name || ""}`),
});

const route = useRoute();
const client = useSanctumClient();

const loading = ref(true);

const form = ref({
  show_rundown_on_home_page: false,
  show_search_bar: true,
  show_location_filter: true,
  show_all_rundown_details: false,
  show_brand_preview_on_home_page: false,
});

// Snapshot of the last persisted payload. Auto-save no-ops when nothing
// changed — e.g. focusing then blurring a field without editing it.
let lastSavedSnapshot = null;
let saving = false;
let savePending = false;

function buildPayload() {
  return {
    rundown: {
      show_rundown_on_home_page: form.value.show_rundown_on_home_page,
      show_search_bar: form.value.show_search_bar,
      show_location_filter: form.value.show_location_filter,
      show_all_rundown_details: form.value.show_all_rundown_details,
    },
    brands: {
      show_brand_preview_on_home_page: form.value.show_brand_preview_on_home_page,
    },
  };
}

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}`);
    const settings = response.data?.settings ?? {};
    const rundown = settings.website_settings?.rundown ?? {};
    const brands = settings.website_settings?.brands ?? {};

    form.value = {
      show_rundown_on_home_page: rundown.show_rundown_on_home_page ?? false,
      show_search_bar: rundown.show_search_bar ?? true,
      show_location_filter: rundown.show_location_filter ?? true,
      show_all_rundown_details: rundown.show_all_rundown_details ?? false,
      show_brand_preview_on_home_page: brands.show_brand_preview_on_home_page ?? false,
    };
    lastSavedSnapshot = JSON.stringify(buildPayload());
  } catch (err) {
    toast.error("Failed to load website settings");
  } finally {
    loading.value = false;
  }
}

async function save() {
  const payload = buildPayload();
  const snapshot = JSON.stringify(payload);

  // Nothing changed since the last save — skip the request entirely.
  if (snapshot === lastSavedSnapshot) {
    return;
  }

  // Serialize overlapping saves: queue one more run after the current finishes.
  if (saving) {
    savePending = true;
    return;
  }

  saving = true;
  try {
    await client(`/api/projects/${route.params.username}/website-settings`, {
      method: "PATCH",
      body: payload,
    });
    lastSavedSnapshot = snapshot;
    toast.success("Website settings updated");
  } catch (err) {
    toast.error("Failed to save", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving = false;
    if (savePending) {
      savePending = false;
      save();
    }
  }
}

// Section toggles persist immediately on change.
watch(
  () => [
    form.value.show_rundown_on_home_page,
    form.value.show_search_bar,
    form.value.show_location_filter,
    form.value.show_all_rundown_details,
    form.value.show_brand_preview_on_home_page,
  ],
  () => save()
);

onMounted(() => {
  load();
});
</script>
