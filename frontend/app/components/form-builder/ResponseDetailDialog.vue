<template>
  <DialogResponsive v-model:open="isOpen" dialog-max-width="600px" :overflow-content="true">
    <template #default>
      <div v-if="response" class="px-4 pb-10 md:px-6 md:py-5">
        <!-- Header: title + meta on the left, status away from the close button -->
        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2 md:pe-9">
          <div class="min-w-0">
            <h3 class="text-foreground text-lg font-semibold tracking-tighter">Response details</h3>
            <div
              class="text-muted-foreground mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs tracking-tight tabular-nums sm:text-sm"
            >
              <span v-if="response.submitted_at" class="flex items-center gap-x-1">
                <Icon name="lucide:clock" class="size-3.5" />
                {{ $dayjs(response.submitted_at).format("MMMM D, YYYY [at] h:mm A") }}
              </span>
              <span v-if="response.ip_address" class="flex items-center gap-x-1">
                <Icon name="lucide:network" class="size-3.5" />
                {{ response.ip_address }}
              </span>
            </div>
          </div>

          <Select
            :model-value="response.status"
            @update:model-value="$emit('update-status', response, $event)"
          >
            <SelectTrigger class="h-8 w-32" aria-label="Response status">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem v-for="s in RESPONSE_STATUS_OPTIONS" :key="s.value" :value="s.value">
                <div class="flex items-center gap-x-1.5">
                  <Icon :name="s.icon" class="size-3.5 shrink-0" :class="s.color" />
                  <span>{{ s.label }}</span>
                </div>
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Answers as a divided list so each Q&A reads as one row -->
        <div class="divide-border/70 mt-5 divide-y rounded-xl border">
          <!-- Respondent email -->
          <div v-if="response.respondent_email" class="space-y-1 px-4 py-3">
            <div
              class="text-muted-foreground flex items-center gap-x-1.5 text-xs font-medium tracking-tight sm:text-sm"
            >
              <Icon name="lucide:mail" class="size-3.5" />
              Email
            </div>
            <a
              :href="`mailto:${response.respondent_email}`"
              class="text-primary block truncate ps-5 text-sm tracking-tight underline-offset-2 hover:underline"
            >
              {{ response.respondent_email }}
            </a>
          </div>

          <!-- Answers -->
          <div v-for="field in answerFields" :key="field.ulid" class="space-y-1 px-4 py-3">
            <div
              class="text-muted-foreground flex items-center gap-x-1.5 text-xs font-medium tracking-tight sm:text-sm"
            >
              <Icon :name="getTypeIcon(field.type)" class="size-3.5" />
              {{ field.label }}
            </div>

            <!-- Value, indented to line up with the label text (icon width + gap) -->
            <div class="ps-5">
            <!-- Rich text -->
            <div
              v-if="field.type === 'rich_text' && valueOf(field)"
              class="prose prose-sm dark:prose-invert max-w-none text-sm tracking-tight"
              v-html="sanitizeHtml(valueOf(field))"
            />

            <!-- Color -->
            <div v-else-if="field.type === 'color' && valueOf(field)" class="flex items-center gap-x-2">
              <span
                class="border-border inline-block size-5 rounded border"
                :style="{ backgroundColor: valueOf(field) }"
              />
              <span class="font-mono text-sm tracking-tight">{{ valueOf(field) }}</span>
            </div>

            <!-- Country -->
            <div v-else-if="field.type === 'country' && valueOf(field)" class="flex items-center gap-x-2">
              <Flag v-if="countryCode(valueOf(field))" :country="countryCode(valueOf(field))" />
              <span class="text-sm tracking-tight">{{ valueOf(field) }}</span>
            </div>

            <!-- URL -->
            <a
              v-else-if="field.type === 'url' && valueOf(field)"
              :href="valueOf(field)"
              target="_blank"
              rel="noopener noreferrer"
              class="text-primary block truncate text-sm tracking-tight underline-offset-2 hover:underline"
            >
              {{ valueOf(field) }}
            </a>

            <!-- File -->
            <div v-else-if="field.type === 'file' && filePaths(field).length" class="space-y-1.5">
              <button
                v-for="(path, index) in filePaths(field)"
                :key="path"
                type="button"
                :disabled="downloadingKey === `${field.ulid}-${index}`"
                class="bg-muted/50 border-border hover:bg-muted flex w-full items-center gap-x-2 rounded-md border px-3 py-2 text-left transition-colors disabled:opacity-50"
                @click="downloadFile(field, index, path)"
              >
                <Icon name="lucide:file" class="text-muted-foreground size-4 shrink-0" />
                <span class="min-w-0 flex-1 truncate text-sm tracking-tight">{{ fileName(path) }}</span>
                <Spinner v-if="downloadingKey === `${field.ulid}-${index}`" class="size-4 shrink-0" />
                <Icon v-else name="lucide:download" class="text-muted-foreground size-4 shrink-0" />
              </button>
            </div>

            <!-- Empty answer -->
            <div
              v-else-if="displayValue(field) === '-'"
              class="text-muted-foreground/60 text-sm tracking-tight"
            >
              No answer
            </div>

            <!-- Default -->
            <div v-else class="text-sm tracking-tight tabular-nums whitespace-pre-wrap">
              {{ displayValue(field) }}
            </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </DialogResponsive>
</template>

<script setup>
import countries from "@/data/countries.json";
import { Flag } from "@/components/ui/flag";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { RESPONSE_STATUS_OPTIONS } from "@/lib/formBuilderStatus";
import { fileName, formatResponseValue, getTypeIcon } from "@/lib/formFieldTypes";
import { toast } from "vue-sonner";

const props = defineProps({
  response: { type: Object, default: null },
  form: { type: Object, required: true },
});

defineEmits(["update-status"]);

const isOpen = defineModel("open", { type: Boolean, default: false });

const { $dayjs } = useNuxtApp();
const client = useSanctumClient();
const { sanitizeHtml } = useSanitize();

const answerFields = computed(() =>
  [...(props.form?.fields || [])]
    .filter((f) => f.type !== "section")
    .sort((a, b) => (a.order_column || 0) - (b.order_column || 0))
);

const valueOf = (field) => props.response?.response_data?.[field.ulid] ?? null;

const displayValue = (field) => formatResponseValue(field, valueOf(field));

const filePaths = (field) => {
  const value = valueOf(field);
  if (!value) return [];
  return Array.isArray(value) ? value : [value];
};

const countryCode = (name) => countries.find((c) => c.label === name)?.value || null;

const downloadingKey = ref(null);

const downloadFile = async (field, index, path) => {
  downloadingKey.value = `${field.ulid}-${index}`;
  try {
    const blob = await client(
      `/api/forms/${props.form.slug}/responses/${props.response.ulid}/files/${field.ulid}?index=${index}`,
      { responseType: "blob" }
    );
    const url = window.URL.createObjectURL(new Blob([blob]));
    const link = document.createElement("a");
    link.href = url;
    link.download = fileName(path);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  } catch (error) {
    toast.error("Failed to download file", {
      description: error?.data?.message || error?.message || "An error occurred",
    });
  } finally {
    downloadingKey.value = null;
  }
};
</script>
