<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-y-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-semibold tracking-tight">Partners</h2>
        <p class="text-muted-foreground text-sm tracking-tight">
          Manage partner categories and their logos for this event.
        </p>
      </div>

      <div class="flex shrink-0 gap-2">
        <button
          @click="copyDialogOpen = true"
          class="border-border hover:bg-muted flex items-center gap-x-1.5 rounded-md border px-2.5 py-1.5 text-sm tracking-tight transition-[transform,background-color] duration-150 ease-out active:scale-98 motion-reduce:transition-none"
        >
          <Icon name="hugeicons:copy-01" class="size-4 shrink-0" />
          <span>Copy from Event</span>
        </button>
        <button
          @click="addCategoryDialogOpen = true"
          class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center gap-x-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium tracking-tight transition-[transform,background-color] duration-150 ease-out active:scale-98 motion-reduce:transition-none"
        >
          <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
          <span>Add Category</span>
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <Icon name="svg-spinners:ring-resize" class="text-muted-foreground size-6" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!categories.length"
      class="flex flex-col items-center justify-center gap-y-4 py-16 text-center"
    >
      <div
        class="*:bg-background/80 *:squircle text-muted-foreground flex items-center -space-x-2 *:rounded-lg *:border *:p-3 *:backdrop-blur-sm [&_svg]:size-5"
      >
        <div class="translate-y-1.5 -rotate-6">
          <Icon name="hugeicons:layers-01" />
        </div>
        <div>
          <Icon name="hugeicons:link-02" />
        </div>
        <div class="translate-y-1.5 rotate-6">
          <Icon name="hugeicons:star" />
        </div>
      </div>
      <div class="space-y-1">
        <h3 class="font-semibold tracking-tight">No partner categories yet</h3>
        <p class="text-muted-foreground max-w-sm text-sm tracking-tight">
          Create categories like "Supported by", "Media Partners", etc. then add partners to each
          category.
        </p>
      </div>
    </div>

    <!-- Category sections (styled like the public Credits section) -->
    <div v-else ref="categoriesContainer" class="flex w-full flex-wrap gap-x-2 gap-y-8">
      <div
        v-for="category in categories"
        :key="category.id"
        class="group/cat hover:bg-pattern-diagonal border-border relative flex grow flex-col items-center justify-center gap-y-4 rounded-xl border px-4 py-10 [--pattern-fg:var(--color-primary)]/3 sm:px-6 dark:[--pattern-fg:var(--color-primary)]/10"
        @dragenter.prevent="onZoneDragEnter(category, $event)"
        @dragover.prevent
        @dragleave="onZoneDragLeave(category)"
        @drop.prevent="onZoneDrop(category, $event)"
      >
        <!-- Floating category badge (matches Credits) -->
        <span
          class="text-primary bg-background xs:text-sm absolute top-0 left-1/2 flex max-w-[calc(100%-1rem)] -translate-x-1/2 -translate-y-1/2 items-center rounded-lg px-2.5 py-1 text-xs font-semibold tracking-tighter text-nowrap"
        >
          <span class="truncate">{{ category.name }}</span>
        </span>

        <!-- Drag handle (top-left, always visible) -->
        <button
          type="button"
          class="category-drag-handle bg-background text-muted-foreground hover:bg-muted hover:text-foreground absolute top-1 left-1 z-20 inline-flex size-7 cursor-grab items-center justify-center rounded-md border shadow-xs transition-[transform,background-color,color] duration-150 ease-out active:scale-98 active:cursor-grabbing motion-reduce:transition-none"
          v-tippy="'Drag to reorder'"
        >
          <Icon name="hugeicons:drag-drop-vertical" class="size-4" />
        </button>

        <!-- Actions menu (top-right, always visible) -->
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <button
              type="button"
              class="bg-background text-muted-foreground hover:bg-muted hover:text-foreground absolute top-1 right-1 z-20 inline-flex size-7 items-center justify-center rounded-md border shadow-xs transition-[transform,background-color,color] duration-150 ease-out active:scale-98 motion-reduce:transition-none"
              v-tippy="'Options'"
            >
              <Icon name="hugeicons:more-vertical" class="size-4" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-44">
            <DropdownMenuItem @click="openAddPartner(category)">
              <Icon name="hugeicons:add-01" class="size-4" />
              <span>Add Partner</span>
            </DropdownMenuItem>
            <DropdownMenuItem @click="openEditCategory(category)">
              <Icon name="hugeicons:edit-02" class="size-4" />
              <span>Edit Category</span>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem class="text-destructive" @click="handleDeleteCategory(category)">
              <Icon name="hugeicons:delete-02" class="size-4" />
              <span>Delete Category</span>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>

        <!-- Total item count (bottom-right, aligned with the top-right actions trigger) -->
        <span
          class="bg-background text-muted-foreground hover:text-foreground absolute right-1 bottom-1 z-20 inline-flex size-7 items-center justify-center rounded-md border text-sm tracking-tight shadow-xs"
        >
          {{ category.partners?.length || 0 }}
        </span>

        <!-- Drag-over overlay (drop image logos to bulk-add partners) -->
        <Transition
          enter-active-class="transition-opacity duration-150 ease-out motion-reduce:transition-none"
          enter-from-class="opacity-0"
          leave-active-class="transition-opacity duration-150 ease-in motion-reduce:transition-none"
          leave-to-class="opacity-0"
        >
          <div
            v-if="dropZones[category.id]?.over"
            class="border-primary bg-background/80 text-primary pointer-events-none absolute inset-0 z-40 flex flex-col items-center justify-center gap-y-2 rounded-xl border-2 border-dashed backdrop-blur-sm"
          >
            <Icon name="hugeicons:image-add-02" class="size-6" />
            <p class="text-sm font-medium tracking-tight">Drop logos to add partners</p>
          </div>
        </Transition>

        <!-- Upload progress overlay -->
        <div
          v-if="dropZones[category.id]?.busy"
          class="bg-background/85 text-muted-foreground absolute inset-0 z-40 flex flex-col items-center justify-center gap-y-2 rounded-xl backdrop-blur-sm"
        >
          <Icon name="svg-spinners:ring-resize" class="text-primary size-6" />
          <p class="text-sm font-medium tracking-tight">
            Adding {{ dropZones[category.id].done }}/{{ dropZones[category.id].total }}…
          </p>
        </div>

        <!-- Logos grid (matches Credits) -->
        <div
          :ref="(el) => setPartnerContainerRef(category.id, el)"
          class="flex w-full flex-wrap items-center justify-evenly gap-x-0 gap-y-2"
        >
          <div
            v-for="partner in category.partners"
            :key="partner.pivot_id"
            :data-pivot-id="partner.pivot_id"
            v-tippy="{ content: partner.name, onShow: showTooltipIfContent }"
            class="drag-handle group/logo relative flex cursor-grab items-center justify-center rounded-xl transition-colors duration-200 ease-out active:cursor-grabbing motion-reduce:transition-none dark:hover:bg-white"
            :class="{
              'w-full max-w-48 p-3 xl:max-w-56': category.no_container,
              'aspect-3/2': !category.no_container,
              'h-20 xl:h-24': category.partners.length <= 10,
              'h-18 xl:h-20': category.partners.length > 10,
            }"
          >
            <img
              v-if="partner.partner_logo?.sm || partner.partner_logo?.original"
              :src="partner.partner_logo?.sm || partner.partner_logo?.original"
              :alt="partner.name"
              width="300"
              height="200"
              loading="lazy"
              decoding="async"
              draggable="false"
              class="pointer-events-none max-h-full w-auto max-w-full object-contain transition-[filter] duration-200 ease-out select-none motion-reduce:transition-none dark:brightness-90 dark:contrast-200 dark:grayscale dark:invert-[75%] dark:group-hover/logo:filter-none"
            />
            <div
              v-else
              class="bg-muted text-muted-foreground pointer-events-none flex size-10 items-center justify-center rounded-lg text-sm font-medium"
            >
              {{ partner.name?.charAt(0)?.toUpperCase() }}
            </div>

            <!-- Remove from category (revealed on hover) -->
            <button
              type="button"
              @click.stop="handleRemovePartner(category, partner)"
              @pointerdown.stop
              class="pointer-coarse:bg-background pointer-coarse:text-foreground pointer-coarse:border-border bg-destructive border-destructive absolute top-1 right-1 z-30 inline-flex size-6 items-center justify-center rounded-md border text-white opacity-0 transition-[opacity,transform] duration-150 ease-out group-hover/logo:opacity-100 active:scale-90 motion-reduce:transition-none pointer-coarse:opacity-100"
              v-tippy="`Remove ${partner.name}`"
            >
              <Icon name="hugeicons:cancel-01" class="size-3.5" />
            </button>
          </div>

          <!-- Add partner tile (same size as a logo tile) -->
          <button
            type="button"
            @click="openAddPartner(category)"
            v-tippy="'Add partner'"
            class="border-primary/20 text-muted-foreground hover:border-primary/40 hover:text-foreground hover:bg-muted/50 flex items-center justify-center rounded-xl border border-dashed transition-[transform,background-color,border-color,color] duration-150 ease-out active:scale-98 motion-reduce:transition-none"
            :class="{
              'w-full max-w-48 p-3 xl:max-w-56': category.no_container,
              'aspect-3/2': !category.no_container,
              'h-20 xl:h-24': (category.partners?.length || 0) <= 10,
              'h-18 xl:h-20': (category.partners?.length || 0) > 10,
            }"
          >
            <Icon name="hugeicons:add-01" class="size-5 shrink-0" />
          </button>
        </div>
      </div>
    </div>

    <!-- Add Category Dialog -->
    <DialogResponsive v-model:open="addCategoryDialogOpen" dialog-max-width="24rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">
            {{ editingCategory ? "Edit Category" : "Add Category" }}
          </h3>
          <form @submit.prevent="handleSaveCategory" class="mt-4 space-y-4">
            <div class="space-y-2">
              <Label>Name</Label>
              <Input
                v-model="categoryForm.name"
                placeholder="e.g. Media Partners"
                auto-focus
                required
              />
            </div>
            <div v-if="editingCategory" class="flex items-center gap-x-2">
              <Switch v-model="categoryForm.no_container" />
              <Label class="cursor-pointer">Full-width logo</Label>
            </div>
            <div class="flex justify-end gap-2">
              <Button variant="outline" type="button" @click="addCategoryDialogOpen = false">
                Cancel
              </Button>
              <Button type="submit" :disabled="categorySaving">
                <Spinner v-if="categorySaving" class="size-4" />
                <span v-else>{{ editingCategory ? "Save" : "Create" }}</span>
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Add Partner to Category Dialog -->
    <DialogResponsive
      v-model:open="addPartnerDialogOpen"
      dialog-max-width="28rem"
      :overflow-content="true"
    >
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Add Partner</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            {{
              partnerMode === "create"
                ? "Add a new partner with its logo."
                : "Search for an existing partner, or create a new one."
            }}
          </p>

          <!-- Search mode: pick an existing partner -->
          <form
            v-if="partnerMode === 'search'"
            @submit.prevent="handleAddExisting"
            class="mt-4 space-y-4"
          >
            <div class="space-y-2">
              <Label>Partner</Label>

              <!-- Selected partner -->
              <div
                v-if="selectedPartner"
                class="border-border flex items-center gap-3 rounded-md border p-2"
              >
                <div
                  class="bg-background flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border"
                >
                  <img
                    v-if="
                      selectedPartner.partner_logo?.sm || selectedPartner.partner_logo?.original
                    "
                    :src="
                      selectedPartner.partner_logo?.sm || selectedPartner.partner_logo?.original
                    "
                    class="max-h-full w-auto max-w-full object-contain"
                    alt=""
                  />
                  <span v-else class="text-muted-foreground text-sm font-medium">
                    {{ selectedPartner.name.charAt(0).toUpperCase() }}
                  </span>
                </div>
                <span class="text-sm font-medium tracking-tight">{{ selectedPartner.name }}</span>
                <button
                  type="button"
                  v-tippy="'Change partner'"
                  class="text-muted-foreground hover:bg-muted hover:text-foreground ml-auto inline-flex size-7 shrink-0 items-center justify-center rounded-md"
                  @click="clearSelectedPartner"
                >
                  <Icon name="hugeicons:cancel-01" class="size-4" />
                </button>
              </div>

              <!-- Combobox search -->
              <Combobox v-else :ignore-filter="true">
                <ComboboxAnchor class="w-full">
                  <ComboboxInput
                    v-model="partnerSearchTerm"
                    placeholder="Search partners..."
                    autocomplete="off"
                    auto-focus
                    class="placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-background border-border focus-visible:border-ring focus-visible:ring-ring flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-sm tracking-tight shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[1px]"
                  />
                </ComboboxAnchor>
                <ComboboxList class="z-100 w-(--reka-combobox-trigger-width)">
                  <ComboboxViewport class="max-h-72 p-1">
                    <ComboboxEmpty
                      class="text-muted-foreground px-2 py-6 text-center text-sm tracking-tight"
                    >
                      {{
                        partnerSearchTerm.trim() ? "No partners found." : "Type to search partners."
                      }}
                    </ComboboxEmpty>
                    <ComboboxGroup>
                      <ComboboxItem
                        v-for="p in partnerResults"
                        :key="p.id"
                        :value="p.name"
                        class="data-highlighted:bg-muted flex w-full cursor-default items-center gap-3 rounded-md px-2 py-2 outline-none select-none"
                        @select="selectPartner(p)"
                      >
                        <div
                          class="bg-background flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border"
                        >
                          <img
                            v-if="p.partner_logo?.sm || p.partner_logo?.original"
                            :src="p.partner_logo?.sm || p.partner_logo?.original"
                            class="max-h-full w-auto max-w-full object-contain"
                            alt=""
                          />
                          <span v-else class="text-muted-foreground text-sm font-medium">
                            {{ p.name.charAt(0).toUpperCase() }}
                          </span>
                        </div>
                        <span class="text-sm tracking-tight">{{ p.name }}</span>
                        <Icon
                          v-if="selectedPartner?.id === p.id"
                          name="hugeicons:tick-02"
                          class="ml-auto size-4 shrink-0"
                        />
                      </ComboboxItem>
                    </ComboboxGroup>
                  </ComboboxViewport>
                </ComboboxList>
              </Combobox>
            </div>

            <!-- Create new partner CTA -->
            <button
              v-if="!selectedPartner"
              type="button"
              class="border-border hover:bg-muted text-muted-foreground hover:text-foreground flex w-full items-center justify-center gap-x-1.5 rounded-md border border-dashed py-2 text-sm tracking-tight transition-[transform,background-color,border-color,color] duration-150 ease-out active:scale-99 motion-reduce:transition-none"
              @click="switchToCreateMode"
            >
              <Icon name="hugeicons:add-01" class="size-4 shrink-0" />
              <span>Create a new partner</span>
            </button>

            <div class="flex justify-end gap-2">
              <Button variant="outline" type="button" @click="addPartnerDialogOpen = false">
                Cancel
              </Button>
              <Button type="submit" :disabled="addPartnerSaving || !selectedPartner">
                <Spinner v-if="addPartnerSaving" class="size-4" />
                <span v-else>Add Partner</span>
              </Button>
            </div>
          </form>

          <!-- Create mode: brand-new partner with logo -->
          <form v-else @submit.prevent="handleCreatePartner" class="mt-4 space-y-4">
            <div class="space-y-2">
              <div class="space-y-1">
                <Label>Logo</Label>
                <p class="text-muted-foreground text-xs tracking-tight sm:text-sm">
                  Format PNG dengan background transparan, ukuran 600x400px. Jangan pakai logo warna
                  putih karena tidak terlihat di background terang.
                </p>
              </div>
              <InputFileImage
                v-model="createPartnerLogo"
                container-class="relative isolate aspect-3/2 max-w-40"
              />
            </div>

            <div class="space-y-2">
              <Label>Name</Label>
              <Input
                v-model="createPartnerForm.name"
                placeholder="Partner name"
                auto-focus
                required
              />
            </div>

            <div class="space-y-2">
              <Label>Website URL</Label>
              <Input
                v-model="createPartnerForm.website_url"
                placeholder="https://example.com"
                type="url"
              />
            </div>

            <div class="flex justify-end gap-2">
              <Button variant="outline" type="button" @click="partnerMode = 'search'">Back</Button>
              <Button type="submit" :disabled="addPartnerSaving || !createPartnerForm.name.trim()">
                <Spinner v-if="addPartnerSaving" class="size-4" />
                <span v-else>Create &amp; Add</span>
              </Button>
            </div>
          </form>
        </div>
      </template>
    </DialogResponsive>

    <!-- Copy from Event Dialog -->
    <DialogResponsive v-model:open="copyDialogOpen" dialog-max-width="28rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <h3 class="text-lg font-semibold tracking-tight">Copy Partners from Event</h3>
          <p class="text-muted-foreground mt-1 text-sm tracking-tight">
            Copy all partner categories and their partners from another event.
          </p>

          <div class="mt-4 space-y-4">
            <div v-if="eventsLoading" class="flex justify-center py-4">
              <Spinner class="size-5" />
            </div>
            <div
              v-else-if="!availableEvents.length"
              class="text-muted-foreground py-4 text-center text-sm tracking-tight"
            >
              No other events have partners configured.
            </div>
            <div v-else class="space-y-2">
              <Label>Source Event</Label>
              <Select v-model="selectedSourceEventId">
                <SelectTrigger>
                  <SelectValue placeholder="Select an event" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="ev in availableEvents" :key="ev.id" :value="String(ev.id)">
                    {{ ev.title }} ({{ ev.partner_categories_count }} categories)
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="flex justify-end gap-2">
              <Button variant="outline" type="button" @click="copyDialogOpen = false"
                >Cancel</Button
              >
              <Button
                type="button"
                :disabled="!selectedSourceEventId || copySaving"
                @click="handleCopyFromEvent"
              >
                <Spinner v-if="copySaving" class="size-4" />
                <span v-else>Copy</span>
              </Button>
            </div>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Delete Category Confirm Dialog -->
    <DialogResponsive v-model:open="deleteCategoryDialogOpen" dialog-max-width="24rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-lg font-semibold tracking-tight">Delete category?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            This will delete "{{ deletingCategory?.name }}" and remove all partner associations in
            this category.
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="deleteCategoryDialogOpen = false">
              Cancel
            </Button>
            <Button
              variant="destructive"
              type="button"
              :disabled="deleteCategorySaving"
              @click="confirmDeleteCategory"
            >
              <Spinner v-if="deleteCategorySaving" class="size-4" />
              <span v-else>Delete</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>

    <!-- Remove Partner Confirm Dialog -->
    <DialogResponsive v-model:open="removePartnerDialogOpen" dialog-max-width="24rem">
      <template #default>
        <div class="px-4 pb-10 md:px-6 md:py-5">
          <div class="text-lg font-semibold tracking-tight">Remove partner?</div>
          <p class="text-muted-foreground mt-1.5 text-sm tracking-tight">
            This removes "{{ removingPartner?.partner?.name }}" from "{{
              removingPartner?.category?.name
            }}".
          </p>
          <div class="mt-3 flex justify-end gap-2">
            <Button variant="outline" type="button" @click="removePartnerDialogOpen = false">
              Cancel
            </Button>
            <Button
              variant="destructive"
              type="button"
              :disabled="removePartnerSaving"
              @click="confirmRemovePartner"
            >
              <Spinner v-if="removePartnerSaving" class="size-4" />
              <span v-else>Remove</span>
            </Button>
          </div>
        </div>
      </template>
    </DialogResponsive>
  </div>
</template>

<script setup>
import InputFileImage from "@/components/InputFileImage.vue";
import { Button } from "@/components/ui/button";
import {
  Combobox,
  ComboboxAnchor,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxItem,
  ComboboxList,
  ComboboxViewport,
} from "@/components/ui/combobox";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { useSortableList } from "@/composables/useSortableList";
import { ComboboxInput } from "reka-ui";
import Sortable from "sortablejs";
import { toast } from "vue-sonner";

const props = defineProps({ event: Object, project: Object });

usePageMeta(null, { title: "Partners" });

const route = useRoute();
const client = useSanctumClient();
const { username, eventSlug } = route.params;
const apiBase = `/api/projects/${username}/events/${eventSlug}/partner-categories`;

// Only render the logo tooltip when the partner actually has a name.
const showTooltipIfContent = (instance) => Boolean(instance.props.content);

// --- Data ---
const categories = ref([]);
const loading = ref(true);
// Per-category drag-and-drop upload state, keyed by category id.
const dropZones = reactive({});

const fetchCategories = async () => {
  try {
    loading.value = true;
    const response = await client(apiBase);
    categories.value = response.data;
    for (const c of categories.value) {
      if (!dropZones[c.id]) {
        dropZones[c.id] = { over: false, depth: 0, busy: false, done: 0, total: 0 };
      }
    }
  } catch (err) {
    console.error("Failed to load partner categories:", err);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchCategories);

// --- Drop image logos onto a category to bulk-create partners ---
const hasFiles = (event) => [...(event.dataTransfer?.types || [])].includes("Files");

const onZoneDragEnter = (category, event) => {
  if (!hasFiles(event) || dropZones[category.id]?.busy) return;
  const z = dropZones[category.id];
  if (!z) return;
  z.depth += 1;
  z.over = true;
};

const onZoneDragLeave = (category) => {
  const z = dropZones[category.id];
  if (!z) return;
  z.depth = Math.max(0, z.depth - 1);
  if (!z.depth) z.over = false;
};

const onZoneDrop = async (category, event) => {
  const z = dropZones[category.id];
  if (z) {
    z.depth = 0;
    z.over = false;
  }
  if (z?.busy) return;
  const files = [...(event.dataTransfer?.files || [])].filter((f) => f.type?.startsWith("image/"));
  if (files.length) await uploadPartnerLogos(category, files);
};

const fileToPartnerName = (filename) => {
  const base = filename
    .replace(/\.[^/.]+$/, "")
    .replace(/[-_]+/g, " ")
    .replace(/\s+/g, " ")
    .trim();
  if (!base) return "Partner";
  // Capitalize plain lowercase words, but leave acronyms / mixed-case (BBSP, iD) untouched.
  return base
    .split(" ")
    .map((w) => (w === w.toLowerCase() ? w.charAt(0).toUpperCase() + w.slice(1) : w))
    .join(" ");
};

const findExistingPartner = async (name) => {
  try {
    const res = await client(`/api/partners/search?q=${encodeURIComponent(name)}`);
    return (
      (res.data || []).find((p) => (p.name || "").toLowerCase() === name.toLowerCase()) || null
    );
  } catch {
    return null;
  }
};

const uploadPartnerLogos = async (category, files) => {
  const z = dropZones[category.id];
  z.busy = true;
  z.done = 0;
  z.total = files.length;
  let added = 0;
  let skipped = 0;
  let failed = 0;
  // Names already in this category (also catches duplicates within the same batch).
  const seen = new Set((category.partners || []).map((p) => (p.name || "").toLowerCase()));
  // Serial so partners append in the dropped order.
  for (const file of files) {
    const name = fileToPartnerName(file.name);
    const key = name.toLowerCase();
    try {
      if (seen.has(key)) {
        skipped += 1;
        continue;
      }
      const existing = await findExistingPartner(name);
      if (existing) {
        // Reuse the existing global partner instead of creating a duplicate.
        await client(`${apiBase}/${category.slug}/partners`, {
          method: "POST",
          body: { partner_id: existing.id },
        });
      } else {
        const formData = new FormData();
        formData.append("file", file);
        const upload = await client("/api/tmp-upload", { method: "POST", body: formData });
        if (!upload?.folder) throw new Error("Upload failed");
        await client(`${apiBase}/${category.slug}/partners`, {
          method: "POST",
          body: { partner_name: name, tmp_partner_logo: upload.folder },
        });
      }
      seen.add(key);
      added += 1;
    } catch (_) {
      failed += 1;
    } finally {
      z.done += 1;
    }
  }
  await fetchCategories();
  z.busy = false;
  z.done = 0;
  z.total = 0;

  const summary = [
    added ? `${added} partner${added === 1 ? "" : "s"} added` : null,
    skipped ? `${skipped} skipped (already in category)` : null,
    failed ? `${failed} failed` : null,
  ]
    .filter(Boolean)
    .join(", ");

  if (failed && !added) {
    toast.error(summary || "Failed to add partners");
  } else if (failed) {
    toast.warning(summary, { description: "Some logos couldn't be processed." });
  } else if (skipped && !added) {
    toast.info(summary);
  } else if (added) {
    toast.success(summary);
  }
};

// --- Category sortable ---
const categoriesContainer = ref(null);

useSortableList(categoriesContainer, categories, {
  onReorder: async () => {
    const order = categories.value.map((c) => c.id);
    try {
      await client(`${apiBase}/update-order`, {
        method: "POST",
        body: { order },
      });
    } catch (err) {
      toast.error("Failed to update category order");
    }
  },
  sortableOptions: { handle: ".category-drag-handle" },
});

// --- Partner sortable within categories ---
const partnerContainerRefs = {};
const partnerSortableInstances = {};

const setPartnerContainerRef = (categoryId, el) => {
  if (el) {
    partnerContainerRefs[categoryId] = el;
  }
};

const initPartnerSortables = () => {
  // Destroy existing instances
  Object.values(partnerSortableInstances).forEach((instance) => instance.destroy());
  Object.keys(partnerSortableInstances).forEach((key) => delete partnerSortableInstances[key]);

  // Create new instances per category
  for (const category of categories.value) {
    const el = partnerContainerRefs[category.id];
    if (!el || !category.partners?.length) continue;

    partnerSortableInstances[category.id] = Sortable.create(el, {
      animation: 200,
      handle: ".drag-handle",
      draggable: "[data-pivot-id]",
      ghostClass: "sortable-ghost",
      chosenClass: "sortable-chosen",
      dragClass: "sortable-drag",
      // Touch: long-press to reorder so swiping over the logos scrolls instead of
      // accidentally dragging. Mouse stays instant (delayOnTouchOnly).
      delay: 200,
      delayOnTouchOnly: true,
      touchStartThreshold: 8,
      onStart: () => {
        if (typeof navigator !== "undefined" && navigator.vibrate) navigator.vibrate(15);
      },
      onEnd: async () => {
        // Read new order from DOM
        const items = el.querySelectorAll("[data-pivot-id]");
        const order = Array.from(items).map((item) => Number(item.dataset.pivotId));

        // Update local state
        const cat = categories.value.find((c) => c.id === category.id);
        if (cat) {
          cat.partners = order
            .map((pivotId) => cat.partners.find((p) => p.pivot_id === pivotId))
            .filter(Boolean);
        }

        try {
          await client(`${apiBase}/${category.slug}/partners/update-order`, {
            method: "POST",
            body: { order },
          });
        } catch (err) {
          toast.error("Failed to update partner order");
        }
      },
    });
  }
};

watch(
  () => categories.value,
  async () => {
    await nextTick();
    initPartnerSortables();
  },
  { deep: false }
);

onUnmounted(() => {
  Object.values(partnerSortableInstances).forEach((instance) => instance.destroy());
});

// --- Add/Edit Category ---
const addCategoryDialogOpen = ref(false);
const editingCategory = ref(null);
const categoryForm = reactive({ name: "", no_container: false });
const categorySaving = ref(false);

const openEditCategory = (category) => {
  editingCategory.value = category;
  categoryForm.name = category.name;
  categoryForm.no_container = category.no_container;
  addCategoryDialogOpen.value = true;
};

watch(addCategoryDialogOpen, (open) => {
  if (!open) {
    editingCategory.value = null;
    categoryForm.name = "";
    categoryForm.no_container = false;
  }
});

const handleSaveCategory = async () => {
  categorySaving.value = true;
  try {
    if (editingCategory.value) {
      await client(`${apiBase}/${editingCategory.value.slug}`, {
        method: "PUT",
        body: { name: categoryForm.name, no_container: categoryForm.no_container },
      });
      toast.success("Category updated");
    } else {
      await client(apiBase, {
        method: "POST",
        body: { name: categoryForm.name, no_container: categoryForm.no_container },
      });
      toast.success("Category created");
    }
    addCategoryDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to save category", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    categorySaving.value = false;
  }
};

// --- Delete Category ---
const deleteCategoryDialogOpen = ref(false);
const deletingCategory = ref(null);
const deleteCategorySaving = ref(false);

const handleDeleteCategory = (category) => {
  deletingCategory.value = category;
  deleteCategoryDialogOpen.value = true;
};

const confirmDeleteCategory = async () => {
  deleteCategorySaving.value = true;
  try {
    await client(`${apiBase}/${deletingCategory.value.slug}`, { method: "DELETE" });
    toast.success("Category deleted");
    deleteCategoryDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to delete category", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    deleteCategorySaving.value = false;
  }
};

// --- Add Partner to Category ---
const addPartnerDialogOpen = ref(false);
const partnerMode = ref("search"); // "search" | "create"
const targetCategory = ref(null);
const partnerSearchTerm = ref("");
const selectedPartner = ref(null);
const addPartnerSaving = ref(false);
const partnerResults = ref([]);
const createPartnerForm = reactive({ name: "", website_url: "" });
const createPartnerLogo = ref([]);

const openAddPartner = (category) => {
  targetCategory.value = category;
  partnerMode.value = "search";
  partnerSearchTerm.value = "";
  selectedPartner.value = null;
  partnerResults.value = [];
  createPartnerForm.name = "";
  createPartnerForm.website_url = "";
  createPartnerLogo.value = [];
  addPartnerDialogOpen.value = true;
};

const selectPartner = (partner) => {
  selectedPartner.value = partner;
};

const clearSelectedPartner = () => {
  selectedPartner.value = null;
  partnerSearchTerm.value = "";
  partnerResults.value = [];
};

const switchToCreateMode = () => {
  createPartnerForm.name = partnerSearchTerm.value.trim();
  createPartnerForm.website_url = "";
  createPartnerLogo.value = [];
  partnerMode.value = "create";
};

// Debounced search
let searchTimeout;
watch(partnerSearchTerm, (term) => {
  // Selecting an item sets the term to the partner's name; keep the selection in
  // that case so editing the field is what clears it.
  if (selectedPartner.value && selectedPartner.value.name === term) {
    return;
  }
  selectedPartner.value = null;
  clearTimeout(searchTimeout);
  if (!term || term.trim().length < 1) {
    partnerResults.value = [];
    return;
  }
  searchTimeout = setTimeout(async () => {
    try {
      const response = await client(`/api/partners/search?q=${encodeURIComponent(term.trim())}`);
      partnerResults.value = response.data || [];
    } catch {
      partnerResults.value = [];
    }
  }, 300);
});

const handleAddExisting = async () => {
  if (!targetCategory.value || !selectedPartner.value) return;
  addPartnerSaving.value = true;
  try {
    await client(`${apiBase}/${targetCategory.value.slug}/partners`, {
      method: "POST",
      body: { partner_id: selectedPartner.value.id },
    });
    toast.success("Partner added");
    addPartnerDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to add partner", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    addPartnerSaving.value = false;
  }
};

const handleCreatePartner = async () => {
  if (!targetCategory.value || !createPartnerForm.name.trim()) return;
  addPartnerSaving.value = true;
  try {
    const body = {
      partner_name: createPartnerForm.name.trim(),
      website_url: createPartnerForm.website_url || null,
    };
    const logo = createPartnerLogo.value?.[0];
    if (logo && logo.startsWith("tmp-")) {
      body.tmp_partner_logo = logo;
    }

    await client(`${apiBase}/${targetCategory.value.slug}/partners`, {
      method: "POST",
      body,
    });
    toast.success("Partner created and added");
    addPartnerDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to create partner", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    addPartnerSaving.value = false;
  }
};

// --- Remove Partner ---
const removePartnerDialogOpen = ref(false);
const removingPartner = ref(null); // { category, partner }
const removePartnerSaving = ref(false);

const handleRemovePartner = (category, partner) => {
  removingPartner.value = { category, partner };
  removePartnerDialogOpen.value = true;
};

const confirmRemovePartner = async () => {
  if (!removingPartner.value) return;
  const { category, partner } = removingPartner.value;
  removePartnerSaving.value = true;

  const cat = categories.value.find((c) => c.id === category.id);
  const index = cat ? cat.partners.findIndex((p) => p.pivot_id === partner.pivot_id) : -1;

  try {
    await client(`${apiBase}/${category.slug}/partners/${partner.pivot_id}`, {
      method: "DELETE",
    });
    // Optimistic removal
    if (cat) {
      cat.partners = cat.partners.filter((p) => p.pivot_id !== partner.pivot_id);
    }
    toast.success("Partner removed", {
      action: {
        label: "Undo",
        onClick: () => undoRemovePartner(category, partner, index),
      },
    });
    removePartnerDialogOpen.value = false;
  } catch (err) {
    toast.error("Failed to remove partner", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    removePartnerSaving.value = false;
  }
};

const undoRemovePartner = async (category, partner, index) => {
  try {
    await client(`${apiBase}/${category.slug}/partners`, {
      method: "POST",
      body: { partner_id: partner.id },
    });
    // Re-adding appends to the end, so restore the partner's original position.
    await fetchCategories();
    const cat = categories.value.find((c) => c.id === category.id);
    const restored = cat?.partners.find((p) => p.id === partner.id);
    if (cat && restored && index >= 0) {
      cat.partners = cat.partners.filter((p) => p.id !== partner.id);
      cat.partners.splice(Math.min(index, cat.partners.length), 0, restored);
      await client(`${apiBase}/${category.slug}/partners/update-order`, {
        method: "POST",
        body: { order: cat.partners.map((p) => p.pivot_id) },
      });
    }
    toast.success("Partner restored");
  } catch (err) {
    toast.error("Failed to restore partner", {
      description: err?.data?.message || err?.message,
    });
  }
};

// --- Copy from Event ---
const copyDialogOpen = ref(false);
const availableEvents = ref([]);
const eventsLoading = ref(false);
const selectedSourceEventId = ref(null);
const copySaving = ref(false);

watch(copyDialogOpen, async (open) => {
  if (open) {
    eventsLoading.value = true;
    try {
      const response = await client("/api/events-with-partners");
      availableEvents.value = (response.data || []).filter((e) => e.id !== props.event?.id);
    } catch {
      availableEvents.value = [];
    } finally {
      eventsLoading.value = false;
    }
  }
});

const handleCopyFromEvent = async () => {
  if (!selectedSourceEventId.value) return;
  copySaving.value = true;
  try {
    const response = await client(`${apiBase}/copy-from-event`, {
      method: "POST",
      body: { source_event_id: Number(selectedSourceEventId.value) },
    });
    toast.success(response.message || "Partners copied");
    copyDialogOpen.value = false;
    await fetchCategories();
  } catch (err) {
    toast.error("Failed to copy partners", {
      description: err?.data?.message || err?.message,
    });
  } finally {
    copySaving.value = false;
  }
};
</script>
