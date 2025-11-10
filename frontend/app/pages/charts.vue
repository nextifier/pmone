<script setup lang="ts">
import ChartAreaGradient from '@/components/charts/ChartAreaGradient.vue'
import ChartAreaInteractive from '@/components/charts/ChartAreaInteractive.vue'
import ChartAreaAxes from '@/components/charts/ChartAreaAxes.vue'
import ChartAreaIcons from '@/components/charts/ChartAreaIcons.vue'
import ChartBarDefault from '@/components/charts/ChartBarDefault.vue'
import ChartBarMultiple from '@/components/charts/ChartBarMultiple.vue'
import ChartBarHorizontal from '@/components/charts/ChartBarHorizontal.vue'
import ChartLineDefault from '@/components/charts/ChartLineDefault.vue'
import ChartLineStep from '@/components/charts/ChartLineStep.vue'
import ChartLineLinear from '@/components/charts/ChartLineLinear.vue'
import ChartPieDonut from '@/components/charts/ChartPieDonut.vue'
import ChartPieStacked from '@/components/charts/ChartPieStacked.vue'
import ChartPieDonutText from '@/components/charts/ChartPieDonutText.vue'
import ChartPieSimple from '@/components/charts/ChartPieSimple.vue'

definePageMeta({
  title: 'Charts',
  description: 'A collection of chart components built with Unovis',
})
</script>

<template>
  <div class="container py-10">
    <div class="mb-10 space-y-2">
      <h1 class="page-title">Charts</h1>
      <p class="page-description">
        A collection of chart components built with Unovis visualization library
      </p>
    </div>

    <div class="grid gap-10 md:gap-12">
      <!-- Area Charts Section -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Area Charts</h2>
          <p class="text-muted-foreground text-sm">
            Area charts with gradient fills and interactive filters
          </p>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
          <ChartAreaGradient />
          <ChartAreaInteractive />
          <ChartAreaAxes />
          <ChartAreaIcons />
        </div>
      </section>

      <!-- Bar Charts Section -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Bar Charts</h2>
          <p class="text-muted-foreground text-sm">
            Vertical and horizontal bar charts for comparing data
          </p>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
          <ChartBarDefault />
          <ChartBarMultiple />
          <ChartBarHorizontal />
        </div>
      </section>

      <!-- Line Charts Section -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Line Charts</h2>
          <p class="text-muted-foreground text-sm">
            Line charts with natural curves, linear, and step styles
          </p>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
          <ChartLineDefault />
          <ChartLineStep />
          <ChartLineLinear />
        </div>
      </section>

      <!-- Pie Charts Section -->
      <section class="space-y-6">
        <div class="space-y-2">
          <h2 class="text-2xl font-semibold tracking-tight">Pie Charts</h2>
          <p class="text-muted-foreground text-sm">
            Donut and pie charts with various styles and nested sections
          </p>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
          <ChartPieDonut />
          <ChartPieStacked />
          <ChartPieDonutText />
          <ChartPieSimple />
        </div>
      </section>
    </div>
  </div>
</template>
