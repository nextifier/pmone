<template>
  <div class="flex flex-col gap-y-4">
    <!-- Search & Filters -->
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative min-w-0 flex-1">
        <Icon
          name="hugeicons:search-01"
          class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
        />
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="searchPlaceholder"
          class="border-input bg-background placeholder:text-muted-foreground focus:ring-ring h-9 w-full rounded-lg border pr-3 pl-9 text-sm tracking-tight outline-none focus:ring-1"
          @input="debouncedSearch"
        />
      </div>

      <slot name="filters" />
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="flex flex-col">
      <div v-for="i in 25" :key="`skeleton-${i}`" class="relative flex gap-x-3 pb-6 last:pb-0">
        <div v-if="i < 25" class="bg-border absolute top-8 bottom-0 left-[15px] w-px" />
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

    <!-- Empty -->
    <div
      v-else-if="!activities.length"
      class="text-muted-foreground flex flex-col items-center justify-center py-12 text-sm tracking-tight"
    >
      <Icon name="hugeicons:activity-01" class="mb-2 size-8 opacity-40" />
      <span>No activity found</span>
    </div>

    <!-- Activity List -->
    <div v-else class="flex flex-col">
      <template v-for="(group, groupIndex) in groupedActivities" :key="group.id">
        <!-- Single entry or first entry of a group -->
        <div class="relative flex gap-x-3 pb-6 last:pb-0">
          <!-- Timeline line -->
          <div
            v-if="groupIndex < groupedActivities.length - 1"
            class="bg-border absolute top-8 bottom-0 left-[15px] w-px"
          />

          <!-- Avatar -->
          <div class="relative">
            <div
              v-if="!group.activity.causer"
              class="bg-muted text-muted-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
            >
              <Icon name="hugeicons:computer-activity" class="size-4" />
            </div>
            <Avatar
              v-else
              :model="group.activity.causer"
              size="sm"
              rounded="rounded-full"
              class="mt-0.5 size-8 shrink-0"
            />
            <!-- Group count badge -->
            <span
              v-if="group.count > 1"
              class="bg-muted-foreground absolute -right-1 -bottom-1 flex size-4 items-center justify-center rounded-full text-[10px] font-medium text-white"
            >
              {{ group.count > 9 ? "9+" : group.count }}
            </span>
          </div>

          <!-- Content -->
          <div class="min-w-0 flex-1 pt-0.5">
            <div class="flex items-start justify-between gap-x-3">
              <p class="text-foreground text-sm leading-snug tracking-tight">
                <span class="font-medium">{{ group.activity.causer_name }}</span>
                {{ truncate(descriptionWithoutCauser(group.activity), 120) }}
                <button
                  v-if="group.count > 1"
                  class="text-muted-foreground hover:text-foreground ml-0.5 text-xs tracking-tight"
                  @click="toggleGroup(group.id)"
                >
                  {{ expandedGroups.has(group.id) ? "(collapse)" : `(+${group.count - 1} more)` }}
                </button>
              </p>
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
              class="mt-1 flex w-fit items-center gap-x-1.5 transition-opacity hover:opacity-70"
            >
              <span
                class="bg-muted text-foreground inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs font-medium tracking-tight sm:text-sm"
              >
                {{ group.activity.subject_type }}
              </span>
              <span
                v-tippy="
                  group.activity.subject_name.length > 80
                    ? group.activity.subject_name
                    : undefined
                "
                class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
              >
                {{ truncate(group.activity.subject_name, 80) }}
              </span>
            </NuxtLink>
            <div
              v-else-if="group.activity.subject_name"
              class="mt-1 flex items-center gap-x-1.5"
            >
              <span
                class="bg-muted text-foreground inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs font-medium tracking-tight sm:text-sm"
              >
                {{ group.activity.subject_type }}
              </span>
              <span
                v-tippy="
                  group.activity.subject_name.length > 80
                    ? group.activity.subject_name
                    : undefined
                "
                class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
              >
                {{ truncate(group.activity.subject_name, 80) }}
              </span>
            </div>

            <!-- Changes -->
            <div
              v-if="group.activity.changes?.length && group.activity.event === 'updated'"
              class="mt-2 flex flex-col gap-y-1"
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
              v-if="subIndex < group.items.length - 2 || groupIndex < groupedActivities.length - 1"
              class="bg-border absolute top-8 bottom-0 left-[15px] w-px"
            />

            <!-- Avatar -->
            <div
              v-if="!subActivity.causer"
              class="bg-muted text-muted-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
            >
              <Icon name="hugeicons:computer-activity" class="size-4" />
            </div>
            <Avatar
              v-else
              :model="subActivity.causer"
              size="sm"
              rounded="rounded-full"
              class="mt-0.5 size-8 shrink-0"
            />

            <!-- Content -->
            <div class="min-w-0 flex-1 pt-0.5">
              <div class="flex items-start justify-between gap-x-3">
                <p class="text-foreground text-sm leading-snug tracking-tight">
                  <span class="font-medium">{{ subActivity.causer_name }}</span>
                  {{ truncate(descriptionWithoutCauser(subActivity), 120) }}
                </p>
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
                class="mt-1 flex w-fit items-center gap-x-1.5 transition-opacity hover:opacity-70"
              >
                <span
                  class="bg-muted text-foreground inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs font-medium tracking-tight sm:text-sm"
                >
                  {{ subActivity.subject_type }}
                </span>
                <span
                  v-tippy="
                    subActivity.subject_name.length > 80
                      ? subActivity.subject_name
                      : undefined
                  "
                  class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
                >
                  {{ truncate(subActivity.subject_name, 80) }}
                </span>
              </NuxtLink>
              <div
                v-else-if="subActivity.subject_name"
                class="mt-1 flex items-center gap-x-1.5"
              >
                <span
                  class="bg-muted text-foreground inline-flex shrink-0 items-center rounded-md px-1.5 py-0.5 text-xs font-medium tracking-tight sm:text-sm"
                >
                  {{ subActivity.subject_type }}
                </span>
                <span
                  v-tippy="
                    subActivity.subject_name.length > 80
                      ? subActivity.subject_name
                      : undefined
                  "
                  class="text-muted-foreground truncate text-xs tracking-tight sm:text-sm"
                >
                  {{ truncate(subActivity.subject_name, 80) }}
                </span>
              </div>

              <!-- Changes -->
              <div
                v-if="subActivity.changes?.length && subActivity.event === 'updated'"
                class="mt-2 flex flex-col gap-y-1"
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
    </div>

    <!-- Pagination -->
    <div
      v-if="activities.length"
      class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
    >
      <div class="flex items-center justify-between gap-x-4">
        <div class="text-muted-foreground text-sm tracking-tight">
          Showing {{ paginationInfo.from }} to {{ paginationInfo.to }} of
          {{ paginationInfo.total }} results.
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
                <button
                  class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                  :disabled="!canGoPrevious"
                  @click="$emit('page', 1)"
                >
                  <Icon name="lucide:chevron-first" class="size-4 shrink-0" />
                </button>
              </PaginationFirst>
              <PaginationPrevious asChild>
                <button
                  class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                  :disabled="!canGoPrevious"
                  @click="$emit('page', meta.current_page - 1)"
                >
                  <Icon name="lucide:chevron-left" class="size-4 shrink-0" />
                </button>
              </PaginationPrevious>
              <PaginationNext asChild>
                <button
                  class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                  :disabled="!canGoNext"
                  @click="$emit('page', meta.current_page + 1)"
                >
                  <Icon name="lucide:chevron-right" class="size-4 shrink-0" />
                </button>
              </PaginationNext>
              <PaginationLast asChild>
                <button
                  class="hover:bg-muted bg-background border-border flex size-8 shrink-0 items-center justify-center rounded-md border active:scale-98"
                  :disabled="!canGoNext"
                  @click="$emit('page', meta.last_page)"
                >
                  <Icon name="lucide:chevron-last" class="size-4 shrink-0" />
                </button>
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
  searchPlaceholder: { type: String, default: "Search activity..." },
  perPage: { type: Number, default: 20 },
  pageSizes: { type: Array, default: () => [10, 20, 50, 100] },
});

const emit = defineEmits(["search", "page", "perPageChange"]);

const { $dayjs } = useNuxtApp();

const searchQuery = ref("");
let searchTimer = null;
const debouncedSearch = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    emit("search", searchQuery.value);
  }, 300);
};

// Group consecutive similar activities
const expandedGroups = ref(new Set());

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
  if (name && desc.startsWith(name)) {
    return desc.slice(name.length);
  }
  return desc;
};

const isDatetimeString = (value) => {
  if (typeof value !== "string") return false;
  return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(value);
};

const formatValue = (value) => {
  if (value === null || value === undefined) return "-";
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (typeof value === "object") return JSON.stringify(value);
  if (isDatetimeString(value)) return $dayjs(value).format("MMM D, YYYY h:mm A");
  return String(value);
};

const truncate = (text, maxLength) => {
  if (!text || text.length <= maxLength) return text;
  return text.slice(0, maxLength - 3) + "...";
};
</script>
