<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-foreground flex items-center gap-2 text-lg font-semibold tracking-tighter">
          Devices
        </h2>
        <p class="text-muted-foreground mt-0.5 text-sm tracking-tight">
          How visitors access your site
        </p>
      </div>
    </div>

    <div v-if="devices && devices.length > 0">
      <!-- Summary Cards -->
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="(device, index) in devices"
          :key="index"
          class="border-border bg-card hover:border-foreground/20 group overflow-hidden rounded-lg border transition-all"
        >
          <div class="flex flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-3">
              <div
                :class="getDeviceColorClass(device.category || device.deviceCategory)"
                class="flex size-12 shrink-0 items-center justify-center rounded-lg"
              >
                <Icon
                  :name="getDeviceIcon(device.category || device.deviceCategory)"
                  class="size-6"
                />
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="text-foreground truncate font-semibold tracking-tight capitalize">
                  {{ device.category || device.deviceCategory }}
                </h3>
                <p class="text-muted-foreground text-xs">
                  {{ calculatePercentage(device) }}% of total
                </p>
              </div>
            </div>

            <!-- Metrics Grid -->
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-0.5">
                <p class="text-muted-foreground text-xs font-medium">Users</p>
                <p class="text-foreground text-xl font-bold tabular-nums">
                  {{ formatNumber(device.users || device.activeUsers || 0) }}
                </p>
              </div>
              <div class="space-y-0.5">
                <p class="text-muted-foreground text-xs font-medium">Sessions</p>
                <p class="text-foreground text-xl font-bold tabular-nums">
                  {{ formatNumber(device.sessions || 0) }}
                </p>
              </div>
            </div>

            <!-- Progress Bar -->
            <div class="space-y-1.5">
              <div class="bg-muted h-2 overflow-hidden rounded-full">
                <div
                  :class="getDeviceBarColorClass(device.category || device.deviceCategory)"
                  class="h-full transition-all"
                  :style="{ width: calculatePercentage(device) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Detailed Stats Table -->
      <div class="border-border bg-card mt-6 overflow-hidden rounded-lg border">
        <table class="w-full">
          <thead class="bg-muted/50 border-border border-b">
            <tr>
              <th
                class="text-muted-foreground px-4 py-3 text-left text-xs font-medium tracking-wider uppercase"
              >
                Device
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase"
              >
                Users
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase"
              >
                Sessions
              </th>
              <th
                class="text-muted-foreground px-4 py-3 text-right text-xs font-medium tracking-wider uppercase"
              >
                Percentage
              </th>
            </tr>
          </thead>
          <tbody class="divide-border divide-y">
            <tr
              v-for="(device, index) in devices"
              :key="index"
              class="hover:bg-muted/30 transition"
            >
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <Icon
                    :name="getDeviceIcon(device.category || device.deviceCategory)"
                    class="text-muted-foreground size-4"
                  />
                  <span class="text-foreground text-sm font-medium capitalize">
                    {{ device.category || device.deviceCategory }}
                  </span>
                </div>
              </td>
              <td class="text-foreground px-4 py-3 text-right text-sm tabular-nums">
                {{ formatNumber(device.users || device.activeUsers || 0) }}
              </td>
              <td class="text-foreground px-4 py-3 text-right text-sm tabular-nums">
                {{ formatNumber(device.sessions || 0) }}
              </td>
              <td class="text-foreground px-4 py-3 text-right text-sm font-medium tabular-nums">
                {{ calculatePercentage(device) }}%
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-else
      class="border-border bg-muted/30 flex flex-col items-center justify-center rounded-lg border p-8 text-center"
    >
      <Icon name="hugeicons:device-not-found" class="text-muted-foreground size-12" />
      <p class="text-muted-foreground mt-3 text-sm">No devices data available</p>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  devices: {
    type: Array,
    default: () => [],
  },
});

const totalUsers = computed(() => {
  if (!props.devices || props.devices.length === 0) return 0;
  return props.devices.reduce((sum, device) => sum + (device.users || device.activeUsers || 0), 0);
});

const calculatePercentage = (device) => {
  if (totalUsers.value === 0) return 0;
  const users = device.users || device.activeUsers || 0;
  return ((users / totalUsers.value) * 100).toFixed(1);
};

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const getDeviceIcon = (category) => {
  const cat = (category || "").toLowerCase();
  if (cat === "mobile") return "hugeicons:mobile-programming-02";
  if (cat === "tablet") return "hugeicons:tablet-02";
  if (cat === "desktop") return "hugeicons:laptop-02";
  return "hugeicons:laptop-phone-sync";
};

const getDeviceColorClass = (category) => {
  const cat = (category || "").toLowerCase();
  if (cat === "mobile") return "bg-blue-500/10 text-blue-700 dark:text-blue-400";
  if (cat === "tablet") return "bg-green-500/10 text-green-700 dark:text-green-400";
  if (cat === "desktop") return "bg-purple-500/10 text-purple-700 dark:text-purple-400";
  return "bg-gray-500/10 text-gray-700 dark:text-gray-400";
};

const getDeviceBarColorClass = (category) => {
  const cat = (category || "").toLowerCase();
  if (cat === "mobile") return "bg-blue-500";
  if (cat === "tablet") return "bg-green-500";
  if (cat === "desktop") return "bg-purple-500";
  return "bg-gray-500";
};
</script>
