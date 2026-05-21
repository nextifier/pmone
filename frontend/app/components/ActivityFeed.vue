<template>
  <div class="flex flex-col gap-y-4">
    <!-- Search & Filters -->
    <div class="flex flex-wrap items-center gap-1.5">
      <div class="relative min-w-0 flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 shrink-0 -translate-y-1/2"
        />
        <Input
          v-model="searchQuery"
          type="text"
          :placeholder="searchPlaceholder"
          class="rounded-lg pr-9 pl-9"
          @input="debouncedSearch"
        />
        <Button
          v-if="searchQuery"
          variant="ghost"
          class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2 size-5 -translate-y-1/2 rounded p-0 hover:bg-transparent"
          aria-label="Clear search"
          @click="clearSearch"
        >
          <Icon name="hugeicons:cancel-01" class="size-4 shrink-0" />
        </Button>
      </div>

      <slot name="filters" />
    </div>

    <slot name="chips" />

    <!-- Loading Skeleton -->
    <div v-if="loading && !activities.length" class="flex flex-col">
      <div
        v-for="i in skeletonCount"
        :key="`skeleton-${i}`"
        class="relative flex gap-x-3 pb-6 last:pb-0"
      >
        <div v-if="i < skeletonCount" class="bg-border absolute top-8 bottom-0 left-[15px] w-px" />
        <Skeleton class="mt-0.5 size-8 shrink-0 rounded-full" />
        <div class="min-w-0 flex-1 space-y-2 pt-0.5">
          <div class="flex items-start justify-between gap-x-3">
            <Skeleton class="h-4 w-48" />
            <Skeleton class="h-3 w-14 shrink-0" />
          </div>
          <Skeleton class="h-3 w-28" />
        </div>
      </div>
    </div>

    <!-- Error -->
    <div
      v-else-if="error"
      class="text-muted-foreground flex flex-col items-center justify-center py-12 text-center text-sm tracking-tight"
    >
      <Icon name="hugeicons:alert-02" class="text-destructive mb-2 size-8 shrink-0 opacity-70" />
      <span>Failed to load activity. Please try again.</span>
      <Button variant="outline" size="sm" class="mt-3 active:scale-98" @click="$emit('retry')">
        <Icon name="hugeicons:reload" class="size-4 shrink-0" />
        <span>Retry</span>
      </Button>
    </div>

    <!-- Empty -->
    <div
      v-else-if="!activities.length"
      class="text-muted-foreground flex flex-col items-center justify-center py-12 text-sm tracking-tight"
    >
      <Icon name="hugeicons:activity-03" class="mb-2 size-8 shrink-0 opacity-40" />
      <span>No activity found</span>
    </div>

    <!-- Activity List -->
    <div v-else class="flex flex-col">
      <template v-for="section in sectionedGroups" :key="section.label">
        <div
          class="text-muted-foreground mb-3 flex items-center gap-x-2 py-1 text-xs font-medium tracking-tight uppercase"
        >
          <span>{{ section.label }}</span>
          <div class="bg-border h-px flex-1" />
        </div>
        <template v-for="(group, groupIndex) in section.groups" :key="group.id">
          <!-- Single entry or first entry of a group -->
          <div class="relative flex gap-x-3 pb-6 last:pb-0">
            <!-- Timeline line -->
            <div
              v-if="groupIndex < section.groups.length - 1"
              class="bg-border absolute top-8 bottom-0 left-[15px] w-px"
            />

            <!-- Avatar -->
            <div class="relative">
              <div
                v-if="!group.activity.causer"
                class="outline-inside text-muted-foreground squircle relative mt-0.5 flex size-8 shrink-0 items-center justify-center"
              >
                <Icon name="hugeicons:ai-setting" class="size-4.5 shrink-0" />
                <span
                  v-if="eventIndicator(group.activity)"
                  :class="[
                    'ring-background absolute -right-0.5 -bottom-0.5 size-2 rounded-full ring-2',
                    eventIndicatorBg(group.activity),
                  ]"
                ></span>
              </div>
              <Avatar
                v-else
                :model="group.activity.causer"
                size="sm"
                rounded="squircle"
                class="mt-0.5 size-8 shrink-0"
                :indicator="eventIndicator(group.activity)"
              />
              <!-- Group count badge -->
              <span
                v-if="group.count > 1"
                class="bg-primary text-primary-foreground absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full text-xs font-medium"
              >
                {{ group.count > 9 ? "9+" : group.count }}
              </span>
            </div>

            <!-- Content -->
            <div class="min-w-0 flex-1 pt-0.5">
              <div class="flex items-start justify-between gap-x-3">
                <component
                  :is="group.activity.subject_url ? NuxtLink : 'p'"
                  :to="group.activity.subject_url || undefined"
                  :class="[
                    'text-foreground text-sm leading-snug tracking-tight',
                    group.activity.subject_url && 'hover:underline',
                  ]"
                >
                  <span class="font-medium">{{ group.activity.causer_name }}</span
                  ><template v-for="(part, i) in descriptionParts(group.activity)" :key="i"
                    ><span v-if="part.quoted" class="text-foreground font-medium tracking-tight">{{
                      part.text
                    }}</span
                    ><template v-else>{{ part.text }}</template></template
                  ><Icon
                    v-if="group.activity.subject_url"
                    name="hugeicons:arrow-up-right-03"
                    class="ml-0.5 inline size-3 shrink-0 align-middle"
                  />
                  <Button
                    v-if="group.count > 1"
                    variant="link"
                    class="text-muted-foreground hover:text-foreground ml-0.5 inline h-auto p-0 text-xs font-normal no-underline hover:no-underline"
                    @click.prevent.stop="toggleGroup(group.id)"
                  >
                    {{ expandedGroups.has(group.id) ? "(collapse)" : `(+${group.count - 1} more)` }}
                  </Button>
                </component>
                <span
                  v-tippy="$dayjs(group.activity.created_at).format('MMMM D, YYYY [at] h:mm A')"
                  class="text-muted-foreground shrink-0 text-xs tracking-tight sm:text-sm"
                >
                  {{ $dayjs(group.activity.created_at).fromNow() }}
                </span>
              </div>

              <!-- Subject badge -->
              <NuxtLink
                v-if="group.activity.subject_name && group.activity.subject_url"
                :to="group.activity.subject_url"
                class="mt-1 flex max-w-full items-center gap-x-1.5 transition-opacity hover:opacity-70"
              >
                <span
                  class="text-foreground inline-flex shrink-0 items-center text-xs font-medium tracking-tight sm:text-sm"
                >
                  {{ group.activity.subject_type }}
                </span>
                <span
                  v-tippy="
                    group.activity.subject_name.length > 80
                      ? group.activity.subject_name
                      : undefined
                  "
                  class="text-muted-foreground min-w-0 truncate text-xs tracking-tight sm:text-sm"
                >
                  {{ truncate(group.activity.subject_name, 80) }}
                </span>
              </NuxtLink>
              <div v-else-if="group.activity.subject_name" class="mt-1 flex items-center gap-x-1.5">
                <span
                  class="text-foreground inline-flex shrink-0 items-center text-xs font-medium tracking-tight sm:text-sm"
                >
                  {{ group.activity.subject_type }}
                </span>
                <span
                  v-tippy="
                    group.activity.subject_name.length > 80
                      ? group.activity.subject_name
                      : undefined
                  "
                  class="text-muted-foreground min-w-0 truncate text-xs tracking-tight sm:text-sm"
                >
                  {{ truncate(group.activity.subject_name, 80) }}
                </span>
              </div>

              <!-- Changes -->
              <div
                v-if="group.activity.changes?.length && group.activity.event === 'updated'"
                class="mt-1 flex flex-col gap-y-1"
              >
                <div
                  v-for="change in group.activity.changes"
                  :key="change.field"
                  class="text-muted-foreground flex flex-wrap items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
                >
                  <span class="font-medium capitalize">{{ change.field }}:</span>
                  <span
                    v-if="change.old !== null && change.old !== undefined"
                    v-tippy="
                      String(formatValue(change.old)).length > 60
                        ? formatValue(change.old)
                        : undefined
                    "
                    class="text-foreground"
                    >{{ truncate(formatValue(change.old), 60) }}</span
                  >
                  <Icon
                    v-if="change.old !== null && change.old !== undefined"
                    name="lucide:arrow-right"
                    class="size-3 shrink-0"
                  />
                  <span
                    v-tippy="
                      String(formatValue(change.new)).length > 60
                        ? formatValue(change.new)
                        : undefined
                    "
                    class="text-foreground"
                    >{{ truncate(formatValue(change.new), 60) }}</span
                  >
                </div>
              </div>
            </div>
          </div>

          <!-- Expanded grouped items -->
          <template v-if="group.count > 1 && expandedGroups.has(group.id)">
            <div
              v-for="(subActivity, subIndex) in group.items.slice(1)"
              :key="subActivity.id"
              class="relative flex gap-x-3 pb-6 last:pb-0"
            >
              <!-- Timeline line -->
              <div
                v-if="subIndex < group.items.length - 2 || groupIndex < section.groups.length - 1"
                class="bg-border absolute top-8 bottom-0 left-[15px] w-px"
              />

              <!-- Avatar -->
              <div
                v-if="!subActivity.causer"
                class="bg-muted text-muted-foreground relative mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
              >
                <Icon name="hugeicons:ai-setting" class="size-5 shrink-0" />
                <span
                  v-if="eventIndicator(subActivity)"
                  :class="[
                    'ring-background absolute -right-0.5 -bottom-0.5 size-2 rounded-full ring-2',
                    eventIndicatorBg(subActivity),
                  ]"
                ></span>
              </div>
              <Avatar
                v-else
                :model="subActivity.causer"
                size="sm"
                rounded="squircle"
                class="mt-0.5 size-8 shrink-0"
                :indicator="eventIndicator(subActivity)"
              />

              <!-- Content -->
              <div class="min-w-0 flex-1 pt-0.5">
                <div class="flex items-start justify-between gap-x-3">
                  <component
                    :is="subActivity.subject_url ? NuxtLink : 'p'"
                    :to="subActivity.subject_url || undefined"
                    :class="[
                      'text-foreground text-sm leading-snug tracking-tight',
                      subActivity.subject_url && 'hover:underline',
                    ]"
                  >
                    <span class="font-medium">{{ subActivity.causer_name }}</span
                    ><template v-for="(part, i) in descriptionParts(subActivity)" :key="i"
                      ><span
                        v-if="part.quoted"
                        class="text-foreground font-medium tracking-tight"
                        >{{ part.text }}</span
                      ><template v-else>{{ part.text }}</template></template
                    ><Icon
                      v-if="subActivity.subject_url"
                      name="hugeicons:arrow-up-right-03"
                      class="ml-0.5 inline size-3.5 shrink-0 align-middle"
                    />
                  </component>
                  <span
                    v-tippy="$dayjs(subActivity.created_at).format('MMMM D, YYYY [at] h:mm A')"
                    class="text-muted-foreground shrink-0 text-xs tracking-tight sm:text-sm"
                  >
                    {{ $dayjs(subActivity.created_at).fromNow() }}
                  </span>
                </div>

                <!-- Subject badge -->
                <NuxtLink
                  v-if="subActivity.subject_name && subActivity.subject_url"
                  :to="subActivity.subject_url"
                  class="mt-1 flex max-w-full items-center gap-x-1.5 transition-opacity hover:opacity-70"
                >
                  <span
                    class="text-foreground inline-flex shrink-0 items-center text-xs font-medium tracking-tight sm:text-sm"
                  >
                    {{ subActivity.subject_type }}
                  </span>
                  <span
                    v-tippy="
                      subActivity.subject_name.length > 80 ? subActivity.subject_name : undefined
                    "
                    class="text-muted-foreground min-w-0 truncate text-xs tracking-tight sm:text-sm"
                  >
                    {{ truncate(subActivity.subject_name, 80) }}
                  </span>
                </NuxtLink>
                <div v-else-if="subActivity.subject_name" class="mt-1 flex items-center gap-x-1.5">
                  <span
                    class="text-foreground inline-flex shrink-0 items-center text-xs font-medium tracking-tight sm:text-sm"
                  >
                    {{ subActivity.subject_type }}
                  </span>
                  <span
                    v-tippy="
                      subActivity.subject_name.length > 80 ? subActivity.subject_name : undefined
                    "
                    class="text-muted-foreground min-w-0 truncate text-xs tracking-tight sm:text-sm"
                  >
                    {{ truncate(subActivity.subject_name, 80) }}
                  </span>
                </div>

                <!-- Changes -->
                <div
                  v-if="subActivity.changes?.length && subActivity.event === 'updated'"
                  class="mt-1 flex flex-col gap-y-1"
                >
                  <div
                    v-for="change in subActivity.changes"
                    :key="change.field"
                    class="text-muted-foreground flex flex-wrap items-center gap-x-1.5 text-xs tracking-tight sm:text-sm"
                  >
                    <span class="font-medium capitalize">{{ change.field }}:</span>
                    <span
                      v-if="change.old !== null && change.old !== undefined"
                      v-tippy="
                        String(formatValue(change.old)).length > 60
                          ? formatValue(change.old)
                          : undefined
                      "
                      class="text-foreground"
                      >{{ truncate(formatValue(change.old), 60) }}</span
                    >
                    <Icon
                      v-if="change.old !== null && change.old !== undefined"
                      name="lucide:arrow-right"
                      class="size-3 shrink-0"
                    />
                    <span
                      v-tippy="
                        String(formatValue(change.new)).length > 60
                          ? formatValue(change.new)
                          : undefined
                      "
                      class="text-foreground"
                      >{{ truncate(formatValue(change.new), 60) }}</span
                    >
                  </div>
                </div>
              </div>
            </div>
          </template>
        </template>
      </template>
    </div>

    <!-- Pagination -->
    <div
      v-if="activities.length"
      class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
    >
      <div class="flex items-center justify-between gap-x-4">
        <div class="text-muted-foreground text-sm tracking-tight">
          Showing {{ paginationInfo.from.toLocaleString() }} to
          {{ paginationInfo.to.toLocaleString() }} of
          {{ paginationInfo.total.toLocaleString() }} results.
        </div>
        <Spinner v-if="loading" />
      </div>

      <div class="flex items-center justify-between gap-x-4">
        <div class="flex items-center gap-x-2">
          <p class="text-muted-foreground hidden text-sm tracking-tight whitespace-nowrap sm:block">
            Rows per page
          </p>
          <Select :modelValue="`${perPage}`" @update:modelValue="handlePageSizeChange">
            <SelectTrigger size="sm">
              <SelectValue :placeholder="`${perPage}`" />
            </SelectTrigger>
            <SelectContent side="top">
              <SelectItem v-for="size in pageSizes" :key="size" :value="`${size}`">
                {{ size }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div>
          <Pagination
            :default-page="meta?.current_page || 1"
            :items-per-page="perPage"
            :total="meta?.total || 0"
          >
            <PaginationContent>
              <PaginationFirst asChild>
                <Button
                  variant="outline"
                  size="iconSm"
                  class="shrink-0 active:scale-98"
                  :disabled="!canGoPrevious"
                  @click="$emit('page', 1)"
                >
                  <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
                </Button>
              </PaginationFirst>
              <PaginationPrevious asChild>
                <Button
                  variant="outline"
                  size="iconSm"
                  class="shrink-0 active:scale-98"
                  :disabled="!canGoPrevious"
                  @click="$emit('page', meta.current_page - 1)"
                >
                  <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
                </Button>
              </PaginationPrevious>
              <PaginationNext asChild>
                <Button
                  variant="outline"
                  size="iconSm"
                  class="shrink-0 active:scale-98"
                  :disabled="!canGoNext"
                  @click="$emit('page', meta.current_page + 1)"
                >
                  <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
                </Button>
              </PaginationNext>
              <PaginationLast asChild>
                <Button
                  variant="outline"
                  size="iconSm"
                  class="shrink-0 active:scale-98"
                  :disabled="!canGoNext"
                  @click="$emit('page', meta.last_page)"
                >
                  <Icon name="lucide:chevron-last" class="size-4 shrink-0" />
                </Button>
              </PaginationLast>
            </PaginationContent>
          </Pagination>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  activities: { type: Array, default: () => [] },
  meta: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: "Search activity..." },
  perPage: { type: Number, default: 20 },
  pageSizes: { type: Array, default: () => [10, 20, 50, 100] },
  initialSearch: { type: String, default: "" },
});

const emit = defineEmits(["search", "page", "perPageChange", "retry"]);

const { $dayjs } = useNuxtApp();

const searchQuery = ref(props.initialSearch || "");
let searchTimer = null;
const debouncedSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    emit("search", searchQuery.value);
  }, 300);
};
const clearSearch = () => {
  searchQuery.value = "";
  clearTimeout(searchTimer);
  emit("search", "");
};

watch(
  () => props.initialSearch,
  (val) => {
    if (val !== searchQuery.value) searchQuery.value = val || "";
  }
);

const skeletonCount = computed(() => Math.min(props.perPage, 10));

const NuxtLink = resolveComponent("NuxtLink");

// Group consecutive similar activities
const expandedGroups = ref(new Set());

// Reset expanded state when the underlying activities change (page/filter change)
watch(
  () => props.activities,
  () => {
    expandedGroups.value = new Set();
  }
);

const groupedActivities = computed(() => {
  const groups = [];
  let currentGroup = null;

  for (const activity of props.activities) {
    const isSimilar =
      currentGroup &&
      currentGroup.activity.causer_id === activity.causer_id &&
      currentGroup.activity.event === activity.event &&
      currentGroup.activity.subject_type === activity.subject_type &&
      currentGroup.activity.human_description === activity.human_description;

    if (isSimilar) {
      currentGroup.items.push(activity);
      currentGroup.count++;
    } else {
      currentGroup = {
        id: activity.id,
        activity,
        items: [activity],
        count: 1,
      };
      groups.push(currentGroup);
    }
  }

  return groups;
});

const sectionedGroups = computed(() => {
  const sections = [];
  let currentLabel = null;
  let currentSection = null;
  const today = $dayjs().startOf("day");
  const yesterday = today.subtract(1, "day");

  for (const group of groupedActivities.value) {
    const d = $dayjs(group.activity.created_at).startOf("day");
    let label;
    if (d.isSame(today)) label = "Today";
    else if (d.isSame(yesterday)) label = "Yesterday";
    else if (d.isSame(today, "year")) label = d.format("MMMM D");
    else label = d.format("MMMM D, YYYY");

    if (label !== currentLabel) {
      currentLabel = label;
      currentSection = { label, groups: [] };
      sections.push(currentSection);
    }
    currentSection.groups.push(group);
  }
  return sections;
});

const EVENT_INDICATOR = {
  created: "success",
  updated: "info",
  deleted: "destructive",
  restored: "warning",
};
const eventIndicator = (activity) => EVENT_INDICATOR[activity?.event] || null;

const INDICATOR_BG = {
  primary: "bg-primary",
  info: "bg-info",
  success: "bg-success",
  warning: "bg-warning",
  destructive: "bg-destructive",
};
const eventIndicatorBg = (activity) => INDICATOR_BG[eventIndicator(activity)];

const descriptionParts = (activity) => {
  const text = truncate(descriptionWithoutCauser(activity), 120) || "";
  const parts = [];
  const regex = /"([^"]+)"/g;
  let last = 0;
  let match;
  while ((match = regex.exec(text)) !== null) {
    if (match.index > last) {
      parts.push({ text: text.slice(last, match.index), quoted: false });
    }
    parts.push({ text: match[1], quoted: true });
    last = match.index + match[0].length;
  }
  if (last < text.length) {
    parts.push({ text: text.slice(last), quoted: false });
  }
  return parts;
};

const toggleGroup = (groupId) => {
  if (expandedGroups.value.has(groupId)) {
    expandedGroups.value.delete(groupId);
  } else {
    expandedGroups.value.add(groupId);
  }
  // Trigger reactivity
  expandedGroups.value = new Set(expandedGroups.value);
};

const paginationInfo = computed(() => {
  if (!props.meta) return { from: 0, to: 0, total: 0 };
  return {
    from: props.meta.from || 0,
    to: props.meta.to || 0,
    total: props.meta.total || 0,
  };
});

const canGoPrevious = computed(() => props.meta?.current_page > 1);
const canGoNext = computed(() => props.meta?.current_page < props.meta?.last_page);

const handlePageSizeChange = (value) => {
  emit("perPageChange", Number(value));
};

/**
 * Remove the causer name prefix from human_description
 * so we can render it separately with font-medium.
 */
const descriptionWithoutCauser = (activity) => {
  const desc = activity.human_description || "";
  const name = activity.causer_name || "";
  const rest = name && desc.startsWith(name) ? desc.slice(name.length) : desc;
  return rest ? " " + rest.trimStart() : "";
};

const isDatetimeString = (value) => {
  if (typeof value !== "string") return false;
  return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(value);
};

// Matches lowercase snake_case / single-word enum tokens (e.g. "pending_payment",
// "expired") so they can be shown as readable text. IDs, emails, URLs and
// already-formatted values (uppercase start, dots, slashes) intentionally fail.
const ENUM_TOKEN_RE = /^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/;

const formatValue = (value) => {
  if (value === null || value === undefined) return "-";
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (typeof value === "object") return JSON.stringify(value);
  if (isDatetimeString(value)) return $dayjs(value).format("MMM D, YYYY h:mm A");
  if (typeof value === "string" && ENUM_TOKEN_RE.test(value)) {
    const words = value.replace(/_/g, " ");
    return words.charAt(0).toUpperCase() + words.slice(1);
  }
  return String(value);
};

const truncate = (text, maxLength) => {
  if (!text || text.length <= maxLength) return text;
  return text.slice(0, maxLength - 3) + "...";
};
</script>
