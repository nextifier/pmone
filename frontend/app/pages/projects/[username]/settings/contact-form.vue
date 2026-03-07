<template>
  <div class="flex flex-col gap-y-5">
    <div class="flex w-full items-start justify-between">
      <div class="space-y-1">
        <h2 class="page-title">Contact Form</h2>
        <p class="page-description">Configure email settings for contact form submissions.</p>
      </div>

      <Button size="sm" :disabled="loading" @click="handleSave">
        <Spinner v-if="loading" />
        Save
        <KbdGroup class="ml-1">
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>

    <template v-if="settingsLoading">
      <div class="flex items-center justify-center py-20">
        <div class="flex items-center gap-x-2">
          <Spinner class="size-4 shrink-0" />
          <span class="text-base tracking-tight">Loading</span>
        </div>
      </div>
    </template>

    <template v-else-if="settingsProject">
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Contact Form Email Configuration</div>
          <p class="text-muted-foreground text-xs tracking-tight">
            Configure email settings for contact form submissions
          </p>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-6">
            <!-- Enable Contact Form -->
            <div class="flex items-center justify-between">
              <div class="space-y-0.5">
                <Label>Enable Contact Form</Label>
                <p class="text-muted-foreground text-xs">
                  Allow external websites to submit contact forms for this project
                </p>
              </div>
              <Switch v-model="form.enabled" />
            </div>

            <template v-if="form.enabled">
              <!-- To Recipients -->
              <div class="space-y-3">
                <Label>To (Recipients)</Label>
                <p class="text-muted-foreground text-xs tracking-tight">
                  Primary email recipients for form submissions
                </p>
                <div class="space-y-2">
                  <div
                    v-for="(email, index) in form.email_config.to"
                    :key="`to-${index}`"
                    class="flex items-center gap-1.5"
                  >
                    <Input
                      v-model="form.email_config.to[index]"
                      type="email"
                      placeholder="email@example.com"
                    />
                    <button
                      type="button"
                      @click="form.email_config.to.splice(index, 1)"
                      class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                    >
                      <Icon name="hugeicons:delete-01" class="size-4" />
                    </button>
                  </div>
                </div>
                <button
                  type="button"
                  @click="form.email_config.to.push('')"
                  class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
                >
                  <Icon name="hugeicons:add-01" class="size-4" />
                  Add To Email
                </button>
              </div>

              <!-- CC Recipients -->
              <div class="space-y-3">
                <Label>CC (Carbon Copy)</Label>
                <p class="text-muted-foreground text-xs tracking-tight">Optional CC recipients</p>
                <div v-if="form.email_config.cc.length > 0" class="space-y-2">
                  <div
                    v-for="(email, index) in form.email_config.cc"
                    :key="`cc-${index}`"
                    class="flex items-center gap-1.5"
                  >
                    <Input
                      v-model="form.email_config.cc[index]"
                      type="email"
                      placeholder="email@example.com"
                    />
                    <button
                      type="button"
                      @click="form.email_config.cc.splice(index, 1)"
                      class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                    >
                      <Icon name="hugeicons:delete-01" class="size-4" />
                    </button>
                  </div>
                </div>
                <button
                  type="button"
                  @click="form.email_config.cc.push('')"
                  class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
                >
                  <Icon name="hugeicons:add-01" class="size-4" />
                  Add CC Email
                </button>
              </div>

              <!-- BCC Recipients -->
              <div class="space-y-3">
                <Label>BCC (Blind Carbon Copy)</Label>
                <p class="text-muted-foreground text-xs tracking-tight">Optional BCC recipients</p>
                <div v-if="form.email_config.bcc.length > 0" class="space-y-2">
                  <div
                    v-for="(email, index) in form.email_config.bcc"
                    :key="`bcc-${index}`"
                    class="flex items-center gap-1.5"
                  >
                    <Input
                      v-model="form.email_config.bcc[index]"
                      type="email"
                      placeholder="email@example.com"
                    />
                    <button
                      type="button"
                      @click="form.email_config.bcc.splice(index, 1)"
                      class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                    >
                      <Icon name="hugeicons:delete-01" class="size-4" />
                    </button>
                  </div>
                </div>
                <button
                  type="button"
                  @click="form.email_config.bcc.push('')"
                  class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
                >
                  <Icon name="hugeicons:add-01" class="size-4" />
                  Add BCC Email
                </button>
              </div>

              <!-- From Name -->
              <div class="space-y-2">
                <Label for="from_name">From Name</Label>
                <Input
                  id="from_name"
                  v-model="form.email_config.from_name"
                  type="text"
                  placeholder="Your Project Name"
                />
                <p class="text-muted-foreground text-xs tracking-tight">
                  The sender name that appears in the email
                </p>
              </div>

              <!-- Reply To -->
              <div class="space-y-2">
                <Label for="reply_to">Reply To Email</Label>
                <Input
                  id="reply_to"
                  v-model="form.email_config.reply_to"
                  type="email"
                  placeholder="noreply@example.com"
                />
                <p class="text-muted-foreground text-xs tracking-tight">
                  Email address for replies (defaults to submitter's email if not set)
                </p>
              </div>
            </template>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import { toast } from "vue-sonner";

const props = defineProps({
  project: Object,
});

const route = useRoute();
const client = useSanctumClient();
const { metaSymbol } = useShortcuts();

const loading = ref(false);

const { data: projectResponse, pending: settingsLoading } = await useLazySanctumFetch(
  () => `/api/projects/${route.params.username}`,
  {
    key: `project-settings-contact-form-${route.params.username}`,
  }
);

const settingsProject = computed(() => projectResponse.value?.data || null);

const form = reactive({
  enabled: false,
  email_config: {
    to: [],
    cc: [],
    bcc: [],
    from_name: "",
    reply_to: "",
  },
});

function populateForm(data) {
  if (!data?.settings?.contact_form) return;

  const cf = data.settings.contact_form;
  form.enabled = cf.enabled ?? false;
  form.email_config.to = [...(cf.email_config?.to || [])];
  form.email_config.cc = [...(cf.email_config?.cc || [])];
  form.email_config.bcc = [...(cf.email_config?.bcc || [])];
  form.email_config.from_name = cf.email_config?.from_name || "";
  form.email_config.reply_to = cf.email_config?.reply_to || "";
}

watch(
  settingsProject,
  (val) => {
    if (val) populateForm(val);
  },
  { immediate: true }
);

async function handleSave() {
  loading.value = true;

  try {
    // Merge with existing settings, only update contact_form
    const currentSettings = settingsProject.value?.settings || {};
    const payload = {
      settings: {
        ...currentSettings,
        contact_form: {
          enabled: form.enabled,
          email_config: {
            to: form.email_config.to.filter((e) => e.trim()),
            cc: form.email_config.cc.filter((e) => e.trim()),
            bcc: form.email_config.bcc.filter((e) => e.trim()),
            from_name: form.email_config.from_name,
            reply_to: form.email_config.reply_to,
          },
        },
      },
    };

    await client(`/api/projects/${route.params.username}`, {
      method: "PUT",
      body: payload,
    });

    toast.success("Contact form settings updated!");
  } catch (err) {
    toast.error(err.response?._data?.message || "Failed to update settings");
  } finally {
    loading.value = false;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => handleSave(),
  },
});

usePageMeta(null, {
  title: computed(() => `Contact Form · ${props.project?.name || ""}`),
});
</script>
