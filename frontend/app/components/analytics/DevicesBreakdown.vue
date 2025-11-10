<template>
  <div class="border-border bg-card rounded-lg border">
    <div class="border-border border-b p-4">
      <h2 class="text-foreground flex items-center gap-2 font-semibold">
        <Icon name="hugeicons:monitor-01" class="size-5" />
        Devices
      </h2>
      <p class="text-muted-foreground text-sm">Device breakdown of your visitors</p>
    </div>
    <div class="p-4">
      <div class="space-y-4">
        <div v-for="(device, index) in devices" :key="index" class="space-y-2">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Icon :name="getDeviceIcon(device.device)" class="text-muted-foreground size-4" />
              <span class="text-foreground capitalize">{{ device.device }}</span>
            </div>
            <div class="text-right">
              <p class="text-foreground font-semibold">
                {{ formatNumber(device.users) }}
              </p>
              <p class="text-muted-foreground text-sm">users</p>
            </div>
          </div>
          <div class="bg-muted h-2 overflow-hidden rounded-full">
            <div
              class="bg-primary h-full transition-all duration-500"
              :style="{
                width: `${calculatePercentage(device.users)}%`,
              }"
            ></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  devices: {
    type: Array,
    required: true,
  },
});

const formatNumber = (value) => {
  if (value === null || value === undefined) return "0";
  return new Intl.NumberFormat().format(Math.round(value));
};

const totalUsers = computed(() => {
  return props.devices.reduce((sum, device) => sum + (device.users || 0), 0);
});

const calculatePercentage = (value) => {
  if (!totalUsers.value) return 0;
  return ((value / totalUsers.value) * 100).toFixed(1);
};

const getDeviceIcon = (device) => {
  const deviceLower = device.toLowerCase();
  if (deviceLower.includes("mobile")) return "hugeicons:smart-phone-01";
  if (deviceLower.includes("tablet")) return "hugeicons:tablet-01";
  if (deviceLower.includes("desktop")) return "hugeicons:monitor-01";
  return "hugeicons:device-access";
};
</script>
