<script setup>
import { ChartRadar } from "@/components/ui/chart";
import ChartCard from "@/components/ui-docs/examples/_shared/ChartCard.vue";

const data = [
  { skill: "Frontend", score: 92 },
  { skill: "Backend", score: 78 },
  { skill: "DevOps", score: 68 },
  { skill: "Design", score: 74 },
  { skill: "Testing", score: 85 },
  { skill: "Security", score: 62 },
];

const config = {
  score: { label: "Proficiency", color: "var(--chart-1)" },
};
</script>

<template>
  <ClientOnly>
    <ChartCard variant="center" size="xs" title="Skill Assessment" description="Team proficiency across key areas">
      <ChartRadar
        :data="data"
        :config="config"
        data-key="score"
        category-key="skill"
        gradient-direction="vertical"
      />
    </ChartCard>
  </ClientOnly>
</template>
