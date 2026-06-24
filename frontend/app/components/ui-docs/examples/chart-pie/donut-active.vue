<script setup>
import { ChartPie } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { plan: "free", users: 12800, fill: "var(--color-free)" },
  { plan: "starter", users: 5400, fill: "var(--color-starter)" },
  { plan: "pro", users: 3600, fill: "var(--color-pro)" },
  { plan: "enterprise", users: 1200, fill: "var(--color-enterprise)" },
];

const config = {
  users: { label: "Users" },
  free: { label: "Free", color: "var(--chart-5)" },
  starter: { label: "Starter", color: "var(--chart-3)" },
  pro: { label: "Pro", color: "var(--chart-2)" },
  enterprise: { label: "Enterprise", color: "var(--chart-1)" },
};

const totalUsers = data.reduce((sum, d) => sum + d.users, 0);
const paidUsers = totalUsers - data[0].users;
const conversionRate = ((paidUsers / totalUsers) * 100).toFixed(1);

const padAngle = (2 * Math.PI) / 180;
</script>

<template>
  <ClientOnly>
    <ChartCard
      variant="center"
      size="xs"
      title="Conversion Funnel"
      :description="`${conversionRate}% of users are on paid plans`"
    >
      <ChartPie
        :data="data"
        :config="config"
        value-key="users"
        name-key="plan"
        :radius="100"
        :arc-width="40"
        :corner-radius="5"
        :pad-angle="padAngle"
        :active-index="2"
        :active-outer-radius="114"
        :center-label="paidUsers.toLocaleString()"
        center-sub-label="Paid Users"
        legend
      />
    </ChartCard>
  </ClientOnly>
</template>
