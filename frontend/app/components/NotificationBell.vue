<template>
  <Popover @update:open="onOpenChange">
    <PopoverTrigger as-child>
      <button
        class="hover:bg-muted relative mr-2 flex size-8 items-center justify-center rounded-lg transition active:scale-98"
      >
        <svg
          class="text-foreground size-5 shrink-0"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
        >
          <g
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            color="currentColor"
          >
            <path
              d="M2.53 14.77c-.213 1.394.738 2.361 1.902 2.843c4.463 1.85 10.673 1.85 15.136 0c1.164-.482 2.115-1.45 1.902-2.843c-.13-.857-.777-1.57-1.256-2.267c-.627-.924-.689-1.931-.69-3.003C19.525 5.358 16.157 2 12 2S4.475 5.358 4.475 9.5c0 1.072-.062 2.08-.69 3.003c-.478.697-1.124 1.41-1.255 2.267"
            />
            <path d="M8 19c.458 1.725 2.076 3 4 3c1.925 0 3.541-1.275 4-3" />
          </g>
        </svg>
        <span
          v-if="unreadCount > 0"
          class="bg-destructive absolute top-0 right-0 flex h-4 items-center justify-center rounded-full text-center text-xs leading-none font-semibold tracking-tighter text-white select-none"
          :class="unreadCount > 9 ? 'w-5.5 translate-x-1' : 'w-4'"
        >
          {{ unreadCount > 9 ? "9+" : unreadCount }}
        </span>
      </button>
    </PopoverTrigger>
    <PopoverContent
      align="end"
      :collision-padding="8"
      class="w-[calc(100vw-1rem)] rounded-xl p-0 sm:w-[400px]"
    >
      <!-- Tabs + Mark all as read -->
      <div class="flex items-center justify-between border-b px-4">
        <div class="relative flex items-center gap-x-4">
          <button
            ref="tabUnreadRef"
            class="relative flex items-center gap-x-1.5 py-2.5 text-sm font-medium tracking-tight transition"
            :class="
              activeTab === 'unread'
                ? 'text-foreground'
                : 'text-muted-foreground hover:text-foreground'
            "
            @click="switchTab('unread')"
          >
            <span>Unread</span>
            <span
              v-if="unreadCount > 0"
              class="bg-destructive flex h-4.5 items-center justify-center rounded-full text-center text-xs leading-none font-semibold tracking-tighter text-white select-none"
              :class="unreadCount > 9 ? 'w-6' : 'w-4.5'"
            >
              {{ unreadCount > 9 ? "9+" : unreadCount }}
            </span>
          </button>
          <button
            ref="tabAllRef"
            class="relative py-2.5 text-sm font-medium tracking-tight transition"
            :class="
              activeTab === 'all'
                ? 'text-foreground'
                : 'text-muted-foreground hover:text-foreground'
            "
            @click="switchTab('all')"
          >
            All
          </button>
          <span
            v-if="indicatorStyle"
            class="bg-foreground absolute bottom-0 h-0.5 rounded-full transition-[left,width] duration-300 ease-in-out"
            :style="indicatorStyle"
          />
        </div>
        <button
          v-if="unreadCount > 0"
          class="text-primary hover:text-primary/80 py-2.5 text-sm font-medium tracking-tight transition"
          @click="handleMarkAllAsRead"
        >
          Mark all as read
        </button>
      </div>

      <!-- Content -->
      <div
        class="max-h-[calc(100dvh-var(--navbar-height-mobile)-3.5rem)] overflow-y-auto overscroll-contain"
      >
        <div
          v-if="loading && notifications.length === 0"
          class="flex items-center justify-center py-8"
        >
          <Icon name="svg-spinners:270-ring-with-bg" class="text-muted-foreground size-5" />
        </div>

        <div
          v-else-if="notifications.length === 0"
          class="flex flex-col items-center justify-center gap-y-3 py-12"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="text-muted-foreground size-8"
            viewBox="0 0 24 24"
          >
            <g
              fill="none"
              stroke="currentColor"
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.5"
              color="currentColor"
            >
              <path
                d="M18 18.167c-4.27 1.272-9.607 1.087-13.568-.554c-1.164-.482-2.115-1.45-1.902-2.843c.13-.857.777-1.57 1.256-2.267c.627-.924.689-1.931.69-3.003c0-1.379.373-2.89 1.024-4m2-2.012A7.5 7.5 0 0 1 11.996 2c4.153 0 7.519 3.358 7.519 7.5c0 1.072.062 2.08.689 3.003c.478.697 1.124 1.41 1.254 2.267c.12.79-.016 1.225-.47 1.73M22 22L2 2"
              />
              <path d="M8 19c.458 1.725 2.076 3 4 3c1.925 0 3.541-1.275 4-3" />
            </g>
          </svg>

          <p class="text-muted-foreground text-sm tracking-tight">
            {{ activeTab === "unread" ? "No unread notifications" : "No notifications yet" }}
          </p>
        </div>

        <div v-else class="flex flex-col gap-y-2.5 py-2.5">
          <div
            v-for="(group, label) in groupedNotifications"
            :key="label"
            class="flex flex-col gap-y-1"
          >
            <div class="text-muted-foreground px-4 text-xs font-medium tracking-tight">
              {{ label }}
            </div>
            <PopoverClose v-for="notification in group" :key="notification.id" as-child>
              <button
                class="hover:bg-muted/50 flex w-full items-start gap-x-3 px-4 py-2 text-left transition"
                @click="handleClick(notification)"
              >
                <div
                  class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border"
                  :class="getIconClasses(notification.data.icon)"
                >
                  <Icon :name="notification.data.icon" class="size-4" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm tracking-tight">
                    {{ notification.data.body }}
                  </p>
                  <p class="text-muted-foreground mt-1 text-xs tracking-tight">
                    {{ $dayjs(notification.created_at).fromNow() }}
                  </p>
                </div>
                <span
                  v-if="!notification.read_at"
                  class="bg-destructive mt-2 size-2 shrink-0 rounded-full"
                />
              </button>
            </PopoverClose>
          </div>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>

<script setup>
import { PopoverClose } from "reka-ui";

const { $dayjs } = useNuxtApp();
const router = useRouter();

const {
  notifications,
  unreadCount,
  loading,
  activeTab,
  fetchNotifications,
  setTab,
  markAsRead,
  markAllAsRead,
  startPolling,
  stopPolling,
} = useNotifications();

const tabUnreadRef = ref(null);
const tabAllRef = ref(null);
const indicatorStyle = ref(null);

const updateIndicator = () => {
  const el = activeTab.value === "unread" ? tabUnreadRef.value : tabAllRef.value;
  if (!el) {
    indicatorStyle.value = null;
    return;
  }
  indicatorStyle.value = {
    left: `${el.offsetLeft}px`,
    width: `${el.offsetWidth}px`,
  };
};

const switchTab = (tab) => {
  setTab(tab);
  nextTick(updateIndicator);
};

onMounted(() => {
  startPolling();
});

onBeforeUnmount(() => {
  stopPolling();
});

const onOpenChange = (open) => {
  if (open) {
    fetchNotifications();
    setTimeout(updateIndicator, 50);
  }
};

const handleClick = (notification) => {
  if (!notification.read_at) {
    markAsRead(notification.id);
  }
  if (notification.data.url) {
    router.push(notification.data.url);
  }
};

const handleMarkAllAsRead = () => {
  markAllAsRead();
};

// Group notifications by time period
const groupedNotifications = computed(() => {
  if (!notifications.value.length) return {};

  const now = $dayjs();
  const groups = {};

  for (const notification of notifications.value) {
    const created = $dayjs(notification.created_at);
    const diffDays = now.diff(created, "day");

    let label;
    if (diffDays === 0) {
      label = "Today";
    } else if (diffDays === 1) {
      label = "Yesterday";
    } else if (diffDays <= 7) {
      label = "Last 7 days";
    } else {
      label = "Older";
    }

    if (!groups[label]) {
      groups[label] = [];
    }
    groups[label].push(notification);
  }

  return groups;
});

const iconColorMap = {
  "hugeicons:task-daily-01":
    "border-blue-200 bg-blue-50 text-blue-600 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400",
  "hugeicons:shopping-bag-01":
    "border-green-200 bg-green-50 text-green-600 dark:border-green-800 dark:bg-green-950 dark:text-green-400",
  "hugeicons:mail-open-love":
    "border-amber-200 bg-amber-50 text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400",
  "hugeicons:user-add-01":
    "border-emerald-200 bg-emerald-50 text-emerald-600 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-400",
  "hugeicons:user-remove-01":
    "border-red-200 bg-red-50 text-red-600 dark:border-red-800 dark:bg-red-950 dark:text-red-400",
  "hugeicons:calendar-03":
    "border-purple-200 bg-purple-50 text-purple-600 dark:border-purple-800 dark:bg-purple-950 dark:text-purple-400",
  "hugeicons:image-02":
    "border-pink-200 bg-pink-50 text-pink-600 dark:border-pink-800 dark:bg-pink-950 dark:text-pink-400",
  "hugeicons:shield-user":
    "border-indigo-200 bg-indigo-50 text-indigo-600 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-400",
  "hugeicons:chart-breakout-square":
    "border-red-200 bg-red-50 text-red-600 dark:border-red-800 dark:bg-red-950 dark:text-red-400",
};

const defaultIconClasses =
  "border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400";

const getIconClasses = (icon) => {
  return iconColorMap[icon] || defaultIconClasses;
};
</script>
