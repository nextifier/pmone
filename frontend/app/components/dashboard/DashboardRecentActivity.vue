<script setup lang="ts">
interface Visitor {
  id: number;
  name: string;
  username: string;
  profile_image: { sm: string; original: string } | null;
}

interface VisitItem {
  id: number;
  visitor: Visitor | null;
  is_anonymous: boolean;
  visited_at: string;
  visited_at_human: string;
}

interface ClickItem {
  id: number;
  link_label: string | null;
  clicker: Visitor | null;
  is_anonymous: boolean;
  clicked_at: string;
  clicked_at_human: string;
}

const props = defineProps<{
  visits: VisitItem[];
  clicks: ClickItem[];
  loading?: boolean;
}>();

const hasActivity = computed(() => {
  return props.visits?.length > 0 || props.clicks?.length > 0;
});

// Combine and sort activities by date
const combinedActivities = computed(() => {
  const activities: Array<{
    type: "visit" | "click";
    data: VisitItem | ClickItem;
    timestamp: Date;
  }> = [];

  props.visits?.forEach((visit) => {
    activities.push({
      type: "visit",
      data: visit,
      timestamp: new Date(visit.visited_at),
    });
  });

  props.clicks?.forEach((click) => {
    activities.push({
      type: "click",
      data: click,
      timestamp: new Date(click.clicked_at),
    });
  });

  return activities.sort((a, b) => b.timestamp.getTime() - a.timestamp.getTime()).slice(0, 8);
});
</script>

<template>
  <Card>
    <CardHeader class="pb-3">
      <div class="flex items-center justify-between">
        <div class="flex flex-col gap-1">
          <CardTitle class="text-base font-semibold tracking-tight">Recent Activity</CardTitle>
          <CardDescription class="text-xs">Latest visits and clicks</CardDescription>
        </div>
        <div class="bg-sky-500/10 flex size-9 items-center justify-center rounded-lg">
          <Icon name="hugeicons:activity-01" class="size-4.5 text-sky-600 dark:text-sky-400" />
        </div>
      </div>
    </CardHeader>
    <CardContent class="px-4 pb-4 pt-0">
      <!-- Loading State -->
      <template v-if="loading">
        <div class="flex flex-col gap-3">
          <div v-for="i in 4" :key="i" class="flex items-center gap-3">
            <Skeleton class="size-8 shrink-0 rounded-full" />
            <div class="flex flex-1 flex-col gap-1">
              <Skeleton class="h-3.5 w-32" />
              <Skeleton class="h-2.5 w-20" />
            </div>
          </div>
        </div>
      </template>

      <!-- Empty State -->
      <template v-else-if="!hasActivity">
        <div class="flex flex-col items-center justify-center gap-2 py-8">
          <div class="bg-muted flex size-12 items-center justify-center rounded-full">
            <Icon name="hugeicons:activity-01" class="text-muted-foreground size-6" />
          </div>
          <p class="text-muted-foreground text-sm">No recent activity</p>
        </div>
      </template>

      <!-- Activity List -->
      <template v-else>
        <ScrollArea class="h-[280px] pr-3">
          <div class="flex flex-col gap-3">
            <div
              v-for="activity in combinedActivities"
              :key="`${activity.type}-${activity.data.id}`"
              class="flex items-center gap-3"
            >
              <!-- Avatar -->
              <div class="relative shrink-0">
                <template v-if="activity.type === 'visit'">
                  <template v-if="(activity.data as VisitItem).visitor?.profile_image">
                    <img
                      :src="(activity.data as VisitItem).visitor!.profile_image!.sm"
                      :alt="(activity.data as VisitItem).visitor!.name"
                      class="size-8 rounded-full object-cover ring-2 ring-white dark:ring-zinc-900"
                    />
                  </template>
                  <template v-else-if="(activity.data as VisitItem).visitor">
                    <div
                      class="flex size-8 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-purple-600 text-xs font-medium text-white ring-2 ring-white dark:ring-zinc-900"
                    >
                      {{ (activity.data as VisitItem).visitor!.name.charAt(0).toUpperCase() }}
                    </div>
                  </template>
                  <template v-else>
                    <div
                      class="bg-muted flex size-8 items-center justify-center rounded-full ring-2 ring-white dark:ring-zinc-900"
                    >
                      <Icon name="hugeicons:user" class="text-muted-foreground size-4" />
                    </div>
                  </template>
                  <!-- Visit indicator -->
                  <div
                    class="absolute -bottom-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"
                  >
                    <Icon name="hugeicons:view" class="size-2.5 text-white" />
                  </div>
                </template>

                <template v-else>
                  <template v-if="(activity.data as ClickItem).clicker?.profile_image">
                    <img
                      :src="(activity.data as ClickItem).clicker!.profile_image!.sm"
                      :alt="(activity.data as ClickItem).clicker!.name"
                      class="size-8 rounded-full object-cover ring-2 ring-white dark:ring-zinc-900"
                    />
                  </template>
                  <template v-else-if="(activity.data as ClickItem).clicker">
                    <div
                      class="flex size-8 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-orange-600 text-xs font-medium text-white ring-2 ring-white dark:ring-zinc-900"
                    >
                      {{ (activity.data as ClickItem).clicker!.name.charAt(0).toUpperCase() }}
                    </div>
                  </template>
                  <template v-else>
                    <div
                      class="bg-muted flex size-8 items-center justify-center rounded-full ring-2 ring-white dark:ring-zinc-900"
                    >
                      <Icon name="hugeicons:user" class="text-muted-foreground size-4" />
                    </div>
                  </template>
                  <!-- Click indicator -->
                  <div
                    class="absolute -bottom-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-violet-500 ring-2 ring-white dark:ring-zinc-900"
                  >
                    <Icon name="hugeicons:cursor-pointer-02" class="size-2.5 text-white" />
                  </div>
                </template>
              </div>

              <!-- Content -->
              <div class="flex min-w-0 flex-1 flex-col">
                <template v-if="activity.type === 'visit'">
                  <span class="text-foreground truncate text-sm font-medium">
                    {{
                      (activity.data as VisitItem).visitor?.name ||
                      (activity.data as VisitItem).visitor?.username ||
                      "Anonymous"
                    }}
                  </span>
                  <span class="text-muted-foreground text-xs">
                    Visited your profile
                    <span class="text-muted-foreground/70">
                      &middot; {{ (activity.data as VisitItem).visited_at_human }}
                    </span>
                  </span>
                </template>
                <template v-else>
                  <span class="text-foreground truncate text-sm font-medium">
                    {{
                      (activity.data as ClickItem).clicker?.name ||
                      (activity.data as ClickItem).clicker?.username ||
                      "Anonymous"
                    }}
                  </span>
                  <span class="text-muted-foreground text-xs">
                    Clicked
                    <span class="font-medium text-violet-600 dark:text-violet-400">
                      {{ (activity.data as ClickItem).link_label || "a link" }}
                    </span>
                    <span class="text-muted-foreground/70">
                      &middot; {{ (activity.data as ClickItem).clicked_at_human }}
                    </span>
                  </span>
                </template>
              </div>
            </div>
          </div>
        </ScrollArea>
      </template>
    </CardContent>
  </Card>
</template>
