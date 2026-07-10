<template>
  <div class="mx-auto flex max-w-2xl flex-col gap-y-6">
    <div class="flex items-start justify-between gap-x-2">
      <div class="space-y-1">
        <h3 class="page-title">Brand Details</h3>
        <p class="page-description">View and edit brand and booth information.</p>
      </div>
      <Button v-if="event?.can_edit" :disabled="saving" size="sm" @click="handleSubmit" class="shrink-0">
        <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
        Save
        <KbdGroup>
          <Kbd>{{ metaSymbol }}</Kbd>
          <Kbd>S</Kbd>
        </KbdGroup>
      </Button>
    </div>

    <form v-if="event?.can_edit" @submit.prevent="handleSubmit" class="grid gap-y-8">
      <!-- Booth Information -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Booth Information</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-4">
            <BrandBoothFields :form="boothForm" />

            <div class="grid grid-cols-2 gap-x-2 gap-y-4">
              <div class="space-y-2">
                <Label for="fascia_name">Fascia Name</Label>
                <Input
                  id="fascia_name"
                  :model-value="boothForm.fascia_name"
                  @update:model-value="(v) => (boothForm.fascia_name = v.toUpperCase())"
                  maxlength="24"
                  placeholder="Fascia name (max 24 chars)"
                />
              </div>
              <div class="space-y-2">
                <Label for="badge_name">Badge Name</Label>
                <Input
                  id="badge_name"
                  v-model="boothForm.badge_name"
                  placeholder="Badge name"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-x-2 gap-y-4">
              <div class="space-y-2">
                <Label>Sales</Label>
                <Combobox v-model="boothForm.sales_id" :ignore-filter="true" :open-on-focus="true">
                  <ComboboxAnchor as-child class="w-full">
                    <div
                      class="border-border relative flex h-9 w-full items-center rounded-md border shadow-xs"
                    >
                      <ComboboxInputPrimitive
                        v-model="salesSearch"
                        :display-value="() => selectedSales?.name || ''"
                        placeholder="Select sales person"
                        class="placeholder:text-muted-foreground h-full w-full rounded-md bg-transparent px-3 text-sm tracking-tight outline-none"
                        autocomplete="off"
                      />
                      <button
                        v-if="boothForm.sales_id"
                        type="button"
                        class="text-muted-foreground hover:text-foreground absolute right-2 shrink-0"
                        @click="
                          boothForm.sales_id = null;
                          salesSearch = '';
                        "
                      >
                        <Icon name="lucide:x" class="size-3.5" />
                      </button>
                    </div>
                  </ComboboxAnchor>
                  <ComboboxList class="w-[var(--reka-combobox-trigger-width)]">
                    <ComboboxViewport>
                      <ComboboxEmpty>No results found.</ComboboxEmpty>
                      <ComboboxItem :value="null">
                        <div class="flex items-center gap-2">
                          <Icon name="hugeicons:border-none-02" class="size-5" />
                          <span>None</span>
                        </div>
                      </ComboboxItem>
                      <ComboboxItem v-for="user in filteredMembers" :key="user.id" :value="user.id">
                        <div class="flex items-center gap-2">
                          <Avatar
                            :model="user"
                            size="sm"
                            class="squircle size-5"
                            rounded="rounded-sm"
                          />
                          <span class="tracking-tight">{{ user.name }}</span>
                        </div>
                        <ComboboxItemIndicator>
                          <Icon name="lucide:check" class="ml-auto size-4" />
                        </ComboboxItemIndicator>
                      </ComboboxItem>
                    </ComboboxViewport>
                  </ComboboxList>
                </Combobox>
              </div>
              <div class="space-y-2">
                <Label>Status</Label>
                <Select v-model="boothForm.status">
                  <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="notes">Notes</Label>
              <Textarea
                id="notes"
                v-model="boothForm.notes"
                rows="3"
                placeholder="Internal notes"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Brand Information -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Brand Information</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-4">
            <div class="grid grid-cols-1 gap-y-6 sm:grid-cols-2 sm:gap-x-6">
              <!-- Profile Image (square avatar) -->
              <div class="space-y-2">
                <Label>Profile Image</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Shown as the brand's avatar on the exhibitors list and brand page.
                </p>
                <InputFileImage
                  v-model="profileImageFiles"
                  v-model:deleteFlag="deleteProfileImage"
                  :initial-image="brandEvent.brand?.profile_image"
                  :min-dimension="1000"
                  allow-svg
                  container-class="relative isolate size-32 rounded-lg"
                  image-class="border-border size-full rounded-lg border object-contain"
                  :container-style="checkerboardStyle"
                />
                <ul class="text-muted-foreground space-y-1 text-xs tracking-tight sm:text-sm">
                  <li class="flex items-center gap-1.5">
                    <Icon name="hugeicons:aspect-ratio" class="size-3.5 shrink-0" />
                    Square, at least 1000x1000px
                  </li>
                  <li class="flex items-center gap-1.5">
                    <Icon name="hugeicons:image-01" class="size-3.5 shrink-0" />
                    JPG, PNG, WebP, or SVG
                  </li>
                  <li class="flex items-start gap-1.5">
                    <Icon name="hugeicons:alert-circle" class="mt-0.5 size-3.5 shrink-0" />
                    <span>Use a solid background so the logo stays visible.</span>
                  </li>
                </ul>
              </div>

              <!-- Brand Logo (raw master file) -->
              <div class="space-y-2">
                <Label>Brand Logo</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Master file for banners, social media, and print. Stored exactly as uploaded,
                  without compression.
                </p>
                <InputFileDownloadCard
                  v-model="logoFiles"
                  v-model:deleteFlag="deleteLogo"
                  :initial-file="brandEvent.brand?.brand_logo"
                  :accepted-file-types="brandLogoAcceptedTypes"
                  skip-optimize
                />
                <ul class="text-muted-foreground space-y-1 text-xs tracking-tight sm:text-sm">
                  <li class="flex items-center gap-1.5">
                    <Icon name="hugeicons:file-01" class="size-3.5 shrink-0" />
                    JPG, PNG, WebP, SVG, PDF, AI, or ZIP
                  </li>
                  <li class="flex items-center gap-1.5">
                    <Icon name="hugeicons:view-off" class="size-3.5 shrink-0" />
                    Not shown on the website.
                  </li>
                </ul>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="brand_name">Brand Name</Label>
              <Input id="brand_name" v-model="brandForm.name" />
            </div>

            <div v-if="businessCategoryOptions.length" class="space-y-2">
              <Label>Business Categories</Label>
              <MultiSelect
                v-model="selectedCategoryOptions"
                :options="availableCategoryOptions"
                placeholder="Add category"
                open-on-focus
              />
            </div>
            <div v-else class="space-y-2">
              <Label>Business Categories</Label>
              <TagsInput v-model="brandForm.business_categories" class="text-sm">
                <TagsInputItem v-for="cat in brandForm.business_categories" :key="cat" :value="cat">
                  <TagsInputItemText />
                  <TagsInputItemDelete />
                </TagsInputItem>
                <TagsInputInput placeholder="Add category" />
              </TagsInput>
            </div>

            <div class="space-y-2">
              <Label for="company_name">Company Name</Label>
              <Input id="company_name" v-model="brandForm.company_name" />
            </div>

            <AddressFields v-model="brandForm.address" :errors="brandErrors" />

            <div class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
              <div class="space-y-2">
                <Label for="company_email">Company Email</Label>
                <Input id="company_email" v-model="brandForm.company_email" type="email" />
              </div>
              <div class="space-y-2">
                <Label for="company_phone">Company Phone</Label>
                <InputPhone v-model="brandForm.company_phone" id="company_phone" />
              </div>
            </div>

            <div class="space-y-2">
              <Label for="description">Description</Label>
              <TipTapEditor
                v-model="brandForm.description"
                model-type="App\Models\Brand"
                collection="description_images"
                :sticky="false"
                min-height="200px"
                placeholder="Write brand description"
              />
            </div>

            <!-- Custom Fields -->
            <template v-if="customFieldDefinitions?.length">
              <div v-for="field in customFieldDefinitions" :key="field.id" class="space-y-2">
                <Label :for="`cf_${field.key}`">
                  {{ field.label }}
                  <span v-if="field.is_required" class="text-destructive">*</span>
                </Label>

                <Input
                  v-if="field.type === 'text'"
                  :id="`cf_${field.key}`"
                  v-model="customFields[field.key]"
                />
                <Input
                  v-else-if="field.type === 'number'"
                  :id="`cf_${field.key}`"
                  v-model="customFields[field.key]"
                  type="number"
                />
                <Textarea
                  v-else-if="field.type === 'textarea'"
                  :id="`cf_${field.key}`"
                  v-model="customFields[field.key]"
                  rows="3"
                />
                <Select v-else-if="field.type === 'select'" v-model="customFields[field.key]">
                  <SelectTrigger :id="`cf_${field.key}`" class="w-full">
                    <SelectValue placeholder="Select" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="option in field.options" :key="option" :value="option">
                      {{ option }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <Select v-else-if="field.type === 'year_select'" v-model="customFields[field.key]">
                  <SelectTrigger :id="`cf_${field.key}`" class="w-full">
                    <SelectValue placeholder="Select year" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="year in yearOptions" :key="year" :value="String(year)">
                      {{ year }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Links -->
      <div class="frame">
        <div class="frame-header">
          <div class="frame-title">Links</div>
        </div>
        <div class="frame-panel">
          <div class="grid grid-cols-1 gap-y-3">
            <div v-if="brandLinks.length > 0" class="space-y-2">
              <div v-for="(link, index) in brandLinks" :key="index" class="flex items-center gap-1.5">
                <div class="min-w-28 sm:min-w-36">
                  <Select
                    v-model="link.label"
                    @update:model-value="(value) => handleLinkLabelChange(index, value)"
                  >
                    <div v-if="link.isCustomLabel" class="relative">
                      <Input
                        v-model="link.label"
                        type="text"
                        placeholder="Enter custom label"
                        class="pr-7"
                      />
                      <SelectTrigger
                        class="absolute top-0 right-0 flex size-8 items-center justify-center border-transparent bg-transparent !p-0 [&_svg]:!m-0"
                      />
                    </div>
                    <SelectTrigger v-else class="w-full">
                      <SelectValue placeholder="Select label" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem v-for="label in PREDEFINED_LINK_LABELS" :key="label" :value="label">
                        {{ label }}
                      </SelectItem>
                      <SelectItem value="Custom">Custom</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <InputLink v-model="link.url" :label="link.label" class="grow" />

                <button
                  type="button"
                  @click="removeLink(index)"
                  class="text-destructive hover:text-destructive/80 flex size-9 items-center justify-center rounded-lg transition"
                >
                  <Icon name="hugeicons:delete-01" class="size-4" />
                </button>
              </div>
            </div>

            <button
              type="button"
              @click="addLink"
              class="text-primary hover:text-primary/80 flex items-center gap-x-1 py-1 text-sm font-medium tracking-tight transition"
            >
              <Icon name="hugeicons:add-01" class="size-4" />
              Add Link
            </button>
          </div>
        </div>
      </div>

      <div>
        <Button type="submit" :disabled="saving" size="sm">
          <Icon v-if="saving" name="svg-spinners:ring-resize" class="mr-1.5 size-4" />
          Save
          <KbdGroup>
            <Kbd>{{ metaSymbol }}</Kbd>
            <Kbd>S</Kbd>
          </KbdGroup>
        </Button>
      </div>
    </form>

    <!-- Document Submissions -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Document Submissions</div>
      </div>
      <div class="frame-panel">
        <div v-if="docSubsLoading" class="flex items-center justify-center py-8">
          <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-5" />
        </div>
        <div v-else-if="!docSubmissions.length" class="py-4 text-center">
          <p class="text-muted-foreground text-sm tracking-tight">
            No applicable documents for this brand.
          </p>
        </div>
        <div v-else class="divide-border divide-y">
          <div
            v-for="item in docSubmissions"
            :key="item.document.id"
            class="flex items-start justify-between gap-x-3 py-3 first:pt-0 last:pb-0"
          >
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-x-2">
                <span class="text-sm font-medium tracking-tight">{{ item.document.title }}</span>
                <Badge
                  v-if="item.document.is_required"
                  variant="outline"
                  class="text-xs font-normal"
                  >Required</Badge
                >
              </div>
              <div class="text-muted-foreground mt-0.5 text-xs tracking-tight sm:text-sm">
                <template v-if="item.status === 'completed'">
                  <template v-if="item.submission?.agreed_at">
                    Agreed by {{ item.submission?.submitter?.name || "Unknown" }}
                  </template>
                  <div v-else-if="documentAnswers(item.document, item.submission).length">
                    <div
                      v-for="answer in documentAnswers(item.document, item.submission)"
                      :key="answer.label"
                    >
                      <span class="text-foreground font-medium">{{ answer.label }}:</span>
                      <template v-if="answer.files?.length">
                        <a
                          v-for="file in answer.files"
                          :key="file.id"
                          :href="file.url"
                          target="_blank"
                          class="text-primary ml-1 inline-flex items-center gap-1 hover:underline"
                        >
                          <Icon name="hugeicons:attachment-02" class="size-3.5 shrink-0" />
                          {{ file.name }}
                        </a>
                      </template>
                      <span v-else class="ml-1">{{ answer.value }}</span>
                    </div>
                  </div>
                  <a
                    v-else-if="item.submission?.submission_file"
                    :href="
                      item.submission.submission_file.url ||
                      item.submission.submission_file.original
                    "
                    target="_blank"
                    class="text-primary mt-2 inline-flex items-center gap-1 hover:underline"
                  >
                    <Icon name="teenyicons:pdf-solid" class="text-destructive size-8 shrink-0" />
                    {{ item.submission.submission_file.alt || "View file" }}
                  </a>
                  <template v-else-if="item.submission?.text_value">
                    {{ item.submission.text_value }}
                  </template>
                  <template v-else> Submitted </template>
                </template>
                <template v-else-if="item.status === 'needs_reagreement'">
                  Needs re-submission (document updated)
                </template>
                <template v-else> Not submitted </template>
              </div>

              <BrandEventDocumentFileHistory
                v-if="item.file_history?.length"
                :history="item.file_history"
              />
            </div>
            <div class="shrink-0">
              <Badge
                :variant="
                  item.status === 'completed'
                    ? 'default'
                    : item.status === 'needs_reagreement'
                      ? 'outline'
                      : 'secondary'
                "
                :class="{
                  'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-400':
                    item.status === 'needs_reagreement',
                }"
                class="text-xs font-normal sm:text-sm"
              >
                {{
                  item.status === "completed"
                    ? "Completed"
                    : item.status === "needs_reagreement"
                      ? "Updated"
                      : "Pending"
                }}
              </Badge>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Members / PIC -->
    <div class="frame">
      <div class="frame-header">
        <div class="frame-title">Members (PIC)</div>
      </div>
      <div class="frame-panel">
        <div class="space-y-4">
          <!-- Member list -->
          <div v-if="members_list.length" class="divide-border divide-y">
            <div
              v-for="member in members_list"
              :key="member.id"
              class="flex items-center justify-between gap-x-3 py-2.5 first:pt-0 last:pb-0"
            >
              <div class="flex items-center gap-x-2.5 overflow-hidden">
                <Avatar
                  :model="{ name: member.name, profile_image: member.avatar }"
                  class="size-8 shrink-0"
                  rounded="rounded-full"
                />
                <div class="min-w-0">
                  <div class="truncate text-sm font-medium tracking-tight">{{ member.name }}</div>
                  <div class="text-muted-foreground truncate text-xs tracking-tight">
                    {{ member.email }}
                  </div>
                </div>
              </div>
              <div v-if="event?.can_edit" class="flex shrink-0 items-center gap-x-1">
                <Tippy>
                  <button
                    type="button"
                    @click="resendInvite(member)"
                    :disabled="sendingInvite === member.id"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted rounded-md p-1.5 transition"
                  >
                    <Icon
                      v-if="sendingInvite === member.id"
                      name="svg-spinners:ring-resize"
                      class="size-4"
                    />
                    <Icon v-else name="hugeicons:mail-send-02" class="size-4" />
                  </button>
                  <template #content>
                    <span class="text-xs tracking-tight">Resend invite email</span>
                  </template>
                </Tippy>
                <Tippy>
                  <button
                    type="button"
                    @click="removeMember(member)"
                    :disabled="removingMember === member.id"
                    class="text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-md p-1.5 transition"
                  >
                    <Icon
                      v-if="removingMember === member.id"
                      name="svg-spinners:ring-resize"
                      class="size-4"
                    />
                    <Icon v-else name="hugeicons:delete-02" class="size-4" />
                  </button>
                  <template #content>
                    <span class="text-xs tracking-tight">Remove member</span>
                  </template>
                </Tippy>
              </div>
            </div>
          </div>
          <p v-else class="text-muted-foreground text-sm tracking-tight">No members yet.</p>

          <!-- Add member -->
          <div v-if="event?.can_edit" class="border-border border-t pt-4">
            <form @submit.prevent="addMember" class="flex items-end gap-x-2">
              <div class="min-w-0 flex-1 space-y-2">
                <Label for="new_member_email">Add Member</Label>
                <Input
                  id="new_member_email"
                  v-model="newMemberEmail"
                  type="email"
                  placeholder="email@example.com"
                />
              </div>
              <Button
                type="submit"
                size="sm"
                :disabled="addingMember || !newMemberEmail.trim()"
                class="shrink-0"
              >
                <Icon v-if="addingMember" name="svg-spinners:ring-resize" class="mr-1 size-4" />
                <Icon v-else name="hugeicons:add-01" class="mr-1 size-4" />
                Invite
              </Button>
            </form>
            <div class="mt-2 flex items-center gap-x-2">
              <Checkbox id="send_invite_email" v-model="sendLoginEmail" />
              <Label for="send_invite_email" class="text-sm font-normal">Send login email</Label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Brand Metadata -->
    <div v-if="brandEvent" class="frame">
      <div class="frame-header">
        <div class="frame-title">Brand Metadata</div>
      </div>
      <div class="frame-panel">
        <div class="grid grid-cols-1 gap-y-4 sm:grid-cols-2">
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">ID</p>
            <p class="font-mono text-sm">{{ brandEvent.brand?.id }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">ULID</p>
            <p class="font-mono text-sm">{{ brandEvent.brand?.ulid }}</p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Created</p>
            <p class="text-sm">
              {{ brandEvent.brand_created_at ? $dayjs(brandEvent.brand_created_at).format("MMM D, YYYY [at] h:mm A") : "-" }}
            </p>
          </div>
          <div>
            <p class="text-muted-foreground text-xs sm:text-sm">Last Updated</p>
            <p class="text-sm">
              {{ brandEvent.brand_updated_at ? $dayjs(brandEvent.brand_updated_at).format("MMM D, YYYY [at] h:mm A") : "-" }}
            </p>
          </div>
          <div v-if="brandEvent.brand_created_by">
            <p class="text-muted-foreground text-xs sm:text-sm">Created By</p>
            <p class="text-sm">{{ brandEvent.brand_created_by.name }}</p>
          </div>
          <div v-if="brandEvent.brand_updated_by">
            <p class="text-muted-foreground text-xs sm:text-sm">Updated By</p>
            <p class="text-sm">{{ brandEvent.brand_updated_by.name }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AddressFields from "@/components/AddressFields.vue";
import { TipTapEditor } from "@/components/ui/tip-tap-editor";
import { Badge } from "@/components/ui/badge";
import { Checkbox } from "@/components/ui/checkbox";
import { MultiSelect } from "@/components/ui/multi-select";
import {
  TagsInput,
  TagsInputInput,
  TagsInputItem,
  TagsInputItemDelete,
  TagsInputItemText,
} from "@/components/ui/tags-input";
import { ComboboxInput as ComboboxInputPrimitive } from "reka-ui";
import { toast } from "vue-sonner";

const props = defineProps({
  brandEvent: Object,
  customFieldDefinitions: { type: Array, default: () => [] },
  customFieldValues: { type: Object, default: () => ({}) },
  businessCategoryOptions: { type: Array, default: () => [] },
});
const emit = defineEmits(["refresh"]);

const route = useRoute();
const client = useSanctumClient();
const { $dayjs } = useNuxtApp();
const project = inject("project");
const event = inject("event");

const saving = ref(false);
const { metaSymbol } = useShortcuts();

// Brand form
const profileImageFiles = ref([]);
const deleteProfileImage = ref(false);
const logoFiles = ref([]);
const deleteLogo = ref(false);
const brandErrors = ref({});

// Raw master-logo collection accepts images plus print/design assets.
const brandLogoAcceptedTypes = [
  "image/jpeg",
  "image/png",
  "image/webp",
  "image/svg+xml",
  "application/pdf",
  "application/postscript",
  "application/illustrator",
  "application/zip",
  "application/x-zip-compressed",
];

// Checkerboard behind the avatar preview so transparent logos are obvious.
const checkerboardStyle = {
  backgroundImage:
    "linear-gradient(45deg, var(--color-muted) 25%, transparent 25%), linear-gradient(-45deg, var(--color-muted) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, var(--color-muted) 75%), linear-gradient(-45deg, transparent 75%, var(--color-muted) 75%)",
  backgroundSize: "16px 16px",
  backgroundPosition: "0 0, 0 8px, 8px -8px, -8px 0px",
};
const brandForm = reactive({
  name: props.brandEvent?.brand?.name || "",
  company_name: props.brandEvent?.brand?.company_name || "",
  company_email: props.brandEvent?.brand?.company_email || "",
  company_phone: props.brandEvent?.brand?.company_phone || "",
  address: {
    street: props.brandEvent?.brand?.address?.street || "",
    city: props.brandEvent?.brand?.address?.city || "",
    province: props.brandEvent?.brand?.address?.province || "",
    country: props.brandEvent?.brand?.address?.country || "",
  },
  description: props.brandEvent?.brand?.description || "",
  business_categories: props.brandEvent?.brand?.business_categories || [],
});

const availableCategoryOptions = computed(() =>
  props.businessCategoryOptions.map((name) => ({ value: name, label: name }))
);
const selectedCategoryOptions = computed({
  get() {
    return (brandForm.business_categories || []).map((name) => ({ value: name, label: name }));
  },
  set(options) {
    brandForm.business_categories = options.map((opt) => opt.value);
  },
});

const customFields = reactive(
  normalizeCustomFieldValues(props.customFieldValues, props.customFieldDefinitions)
);

function normalizeCustomFieldValues(values, definitions) {
  const result = { ...values };
  for (const field of definitions || []) {
    if (field.type === "year_select" && result[field.key] != null) {
      result[field.key] = String(result[field.key]);
    }
  }
  return result;
}

const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
  const years = [];
  for (let y = currentYear; y >= 1950; y--) years.push(y);
  return years;
});

// Booth form
const boothForm = reactive({
  booth_number: props.brandEvent?.booth_number || "",
  booth_size: props.brandEvent?.booth_size || null,
  booth_price: props.brandEvent?.booth_price || null,
  booth_type: props.brandEvent?.booth_type || "",
  fascia_name: props.brandEvent?.fascia_name || "",
  badge_name: props.brandEvent?.badge_name || "",
  sales_id: props.brandEvent?.sales?.id || null,
  status: props.brandEvent?.status || "draft",
  notes: props.brandEvent?.notes || "",
});

// Links
const PREDEFINED_LINK_LABELS = ["Website", "Instagram", "Facebook", "X", "TikTok", "LinkedIn", "YouTube"];

const brandLinks = reactive(
  (props.brandEvent?.brand?.links || []).map((link) => ({
    label: link.label || "",
    url: link.url || "",
    isCustomLabel: !PREDEFINED_LINK_LABELS.includes(link.label),
  }))
);

function addLink() {
  brandLinks.push({ label: "", url: "", isCustomLabel: false });
}

function removeLink(index) {
  brandLinks.splice(index, 1);
}

function handleLinkLabelChange(index, value) {
  if (value === "Custom") {
    brandLinks[index].isCustomLabel = true;
    brandLinks[index].label = "";
  } else {
    brandLinks[index].isCustomLabel = false;
    brandLinks[index].label = value;
  }
}

const members = computed(() => project.value?.members || []);
const salesSearch = ref("");
const selectedSales = computed(
  () => members.value.find((u) => u.id === boothForm.sales_id) || null
);
const filteredMembers = computed(() => {
  const term = salesSearch.value.trim().toLowerCase();
  if (!term) return members.value;
  return members.value.filter((u) => u.name.toLowerCase().includes(term));
});

// Sync forms when brandEvent changes
watch(
  () => props.brandEvent,
  (val) => {
    if (val?.brand) {
      brandForm.name = val.brand.name || "";
      brandForm.company_name = val.brand.company_name || "";
      brandForm.company_email = val.brand.company_email || "";
      brandForm.company_phone = val.brand.company_phone || "";
      brandForm.address = {
        street: val.brand.address?.street || "",
        city: val.brand.address?.city || "",
        province: val.brand.address?.province || "",
        country: val.brand.address?.country || "",
      };
      brandForm.description = val.brand.description || "";
      brandForm.business_categories = val.brand.business_categories || [];
    }
    if (val) {
      boothForm.booth_number = val.booth_number || "";
      boothForm.booth_size = val.booth_size || null;
      boothForm.booth_price = val.booth_price || null;
      boothForm.booth_type = val.booth_type || "";
      boothForm.fascia_name = val.fascia_name || "";
      boothForm.badge_name = val.badge_name || "";
      boothForm.sales_id = val.sales?.id || null;
      boothForm.status = val.status || "draft";
      boothForm.notes = val.notes || "";

      // Sync links
      brandLinks.splice(0, brandLinks.length);
      if (val.brand?.links?.length) {
        brandLinks.push(
          ...val.brand.links.map((link) => ({
            label: link.label || "",
            url: link.url || "",
            isCustomLabel: !PREDEFINED_LINK_LABELS.includes(link.label),
          }))
        );
      }
    }
  }
);

watch(
  () => props.customFieldValues,
  (val) => {
    if (val) {
      const normalized = normalizeCustomFieldValues(val, props.customFieldDefinitions);
      Object.keys(normalized).forEach((key) => {
        customFields[key] = normalized[key];
      });
    }
  }
);

const profileUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}/profile`
);
const boothUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}`
);

async function handleSubmit() {
  saving.value = true;
  brandErrors.value = {};
  try {
    const brandBody = { ...brandForm };

    // Clean address - send null if all empty
    const addr = brandBody.address;
    if (!addr.street && !addr.city && !addr.province && !addr.country) {
      brandBody.address = null;
    }

    if (profileImageFiles.value?.[0] && typeof profileImageFiles.value[0] === "string") {
      brandBody.tmp_profile_image = profileImageFiles.value[0];
    }
    if (deleteProfileImage.value) {
      brandBody.delete_profile_image = true;
    }
    if (logoFiles.value?.[0] && typeof logoFiles.value[0] === "string") {
      brandBody.tmp_brand_logo = logoFiles.value[0];
    }
    if (deleteLogo.value) {
      brandBody.delete_brand_logo = true;
    }
    if (props.customFieldDefinitions?.length) {
      brandBody.project_custom_fields = { ...customFields };
    }

    // Include links (filter out empty ones)
    brandBody.links = brandLinks
      .filter((link) => link.label && link.url)
      .map((link) => ({ label: link.label, url: link.url }));

    const boothBody = { ...boothForm };
    if (!boothBody.booth_type) boothBody.booth_type = null;
    if (boothBody.booth_size === "" || boothBody.booth_size === null) boothBody.booth_size = null;
    if (boothBody.booth_price === "" || boothBody.booth_price === null)
      boothBody.booth_price = null;

    await Promise.all([
      client(profileUrl.value, { method: "PUT", body: brandBody }),
      client(boothUrl.value, { method: "PUT", body: boothBody }),
    ]);

    profileImageFiles.value = [];
    deleteProfileImage.value = false;
    logoFiles.value = [];
    deleteLogo.value = false;

    toast.success("Brand details saved");
    emit("refresh");
  } catch (e) {
    brandErrors.value = e?.data?.errors || {};
    toast.error(e?.data?.message || "Failed to save");
  } finally {
    saving.value = false;
  }
}

// Document Submissions
const docSubmissions = ref([]);
const docSubsLoading = ref(true);
const docSubsUrl = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}/document-submissions`
);

async function fetchDocSubmissions() {
  docSubsLoading.value = true;
  try {
    const res = await client(docSubsUrl.value);
    docSubmissions.value = res.data || [];
  } catch {
    docSubmissions.value = [];
  } finally {
    docSubsLoading.value = false;
  }
}

onMounted(() => fetchDocSubmissions());

// Members / PIC
const membersApiBase = computed(
  () =>
    `/api/projects/${route.params.username}/events/${route.params.eventSlug}/brands/${route.params.brandSlug}/members`
);
const members_list = ref(props.brandEvent?.members || []);
const newMemberEmail = ref("");
const sendLoginEmail = ref(false);
const addingMember = ref(false);
const removingMember = ref(null);
const sendingInvite = ref(null);

watch(
  () => props.brandEvent?.members,
  (val) => {
    if (val) members_list.value = val;
  }
);

async function addMember() {
  if (!newMemberEmail.value.trim()) return;
  addingMember.value = true;
  try {
    await client(membersApiBase.value, {
      method: "POST",
      body: {
        email: newMemberEmail.value.trim(),
        send_login_email: sendLoginEmail.value,
      },
    });
    toast.success("Member invited");
    newMemberEmail.value = "";
    emit("refresh");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to add member");
  } finally {
    addingMember.value = false;
  }
}

async function removeMember(member) {
  removingMember.value = member.id;
  try {
    await client(`${membersApiBase.value}/${member.id}`, { method: "DELETE" });
    members_list.value = members_list.value.filter((m) => m.id !== member.id);
    toast.success("Member removed");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to remove member");
  } finally {
    removingMember.value = null;
  }
}

async function resendInvite(member) {
  sendingInvite.value = member.id;
  try {
    await client(`${membersApiBase.value}/${member.id}/send-invite`, { method: "POST" });
    toast.success("Invite email sent");
  } catch (e) {
    toast.error(e?.data?.message || "Failed to send invite");
  } finally {
    sendingInvite.value = null;
  }
}

defineShortcuts({
  meta_s: {
    usingInput: true,
    handler: () => {
      handleSubmit();
    },
  },
});
</script>
