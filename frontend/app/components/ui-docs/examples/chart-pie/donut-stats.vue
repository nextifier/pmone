<script setup>
import { ChartPie } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { status: "completed", count: 186, fill: "var(--color-completed)" },
  { status: "inProgress", count: 94, fill: "var(--color-inProgress)" },
  { status: "pending", count: 62, fill: "var(--color-pending)" },
  { status: "cancelled", count: 28, fill: "var(--color-cancelled)" },
];

const config = {
  count: { label: "Tasks" },
  completed: { label: "Completed", color: "var(--chart-1)" },
  inProgress: { label: "In Progress", color: "var(--chart-2)" },
  pending: { label: "Pending", color: "var(--chart-3)" },
  cancelled: { label: "Cancelled", color: "var(--chart-5)" },
};

const totalTasks = data.reduce((sum, d) => sum + d.count, 0);
const completionRate = Math.round((data[0].count / totalTasks) * 100);
const centerLabel = `${completionRate}%`;

const padAngle = (2 * Math.PI) / 180;
</script>

<template>
  <ClientOnly>
    <ChartCard variant="center" size="xs" title="Task Status" description="Current sprint task breakdown">
      <ChartPie
        :data="data"
        :config="config"
        value-key="count"
        name-key="status"
        :radius="100"
        :arc-width="35"
        :corner-radius="5"
        :pad-angle="padAngle"
        :center-label="centerLabel"
        center-sub-label="Completed"
        legend
      />
    </ChartCard>
  </ClientOnly>
</template>
