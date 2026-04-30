<template>
  <div class="flex flex-col gap-y-6">
    <h2 class="page-title">Website Settings</h2>

    <p class="text-muted-foreground -mt-3 text-sm tracking-tight">
      Control how the public website renders sections sourced from this project. Changes save automatically.
    </p>

    <div v-if="loading" class="flex items-center justify-center py-12">
      <Spinner class="size-5" />
    </div>

    <div v-else class="frame">
      <div class="flex items-center gap-x-3 px-4 py-3 lg:px-5">
        <Icon name="hugeicons:time-schedule" class="size-5" />
        <div class="min-w-0">
          <h3 class="text-base font-semibold tracking-tight">Rundown</h3>
          <p class="text-muted-foreground text-sm tracking-tight">
            Configure how the Rundown section behaves on the public event website.
          </p>
        </div>
      </div>

      <div class="frame-panel divide-border divide-y">
        <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
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
          </div>
          <Switch
            id="rundown-show-on-home"
            v-model="form.show_rundown_on_home_page"
            :disabled="saving"
          />
        </div>

        <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
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
          <Switch
            id="rundown-show-search"
            v-model="form.show_search_bar"
            :disabled="saving"
          />
        </div>

        <div class="flex items-start justify-between gap-4 px-4 py-4 lg:px-5">
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
          <Switch
            id="rundown-show-all-details"
            v-model="form.show_all_rundown_details"
            :disabled="saving"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
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
const saving = ref(false);

const form = ref({
  show_rundown_on_home_page: false,
  show_search_bar: true,
  show_all_rundown_details: false,
});

let suppressWatcher = true;

async function load() {
  loading.value = true;
  try {
    const response = await client(`/api/projects/${route.params.username}`);
    const settings = response.data?.settings ?? {};
    const rundown = settings.website_settings?.rundown ?? {};

    form.value = {
      show_rundown_on_home_page: rundown.show_rundown_on_home_page ?? false,
      show_search_bar: rundown.show_search_bar ?? true,
      show_all_rundown_details: rundown.show_all_rundown_details ?? false,
    };
  } catch (err) {
    toast.error("Failed to load website settings");
  } finally {
    loading.value = false;
    nextTick(() => {
      suppressWatcher = false;
    });
  }
}

async function persist() {
  saving.value = true;
  try {
    await client(`/api/projects/${route.params.username}/website-settings`, {
      method: "PATCH",
      body: { rundown: { ...form.value } },
    });
    toast.success("Website settings updated");
  } catch (err) {
    toast.error("Failed to save", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    saving.value = false;
  }
}

watch(
  () => ({ ...form.value }),
  () => {
    if (suppressWatcher) return;
    persist();
  },
  { deep: true }
);

onMounted(load);
</script>
