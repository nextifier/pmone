import type { Component, Ref } from "vue"
import { createContext } from "reka-ui"

export { default as ChartContainer } from "./ChartContainer.vue"
export { default as ChartLegendContent } from "./ChartLegendContent.vue"
export { default as ChartTooltipContent } from "./ChartTooltipContent.vue"
export { default as ChartSemiCircle } from "./ChartSemiCircle.vue"

// Line
export { default as ChartLine } from "./ChartLine.vue"
export { default as ChartLineDefault } from "./ChartLineDefault.vue"
export { default as ChartLineLinear } from "./ChartLineLinear.vue"
export { default as ChartLineStep } from "./ChartLineStep.vue"
export { default as ChartLineInteractive } from "./ChartLineInteractive.vue"

// Area
export { default as ChartArea } from "./ChartArea.vue"
export { default as ChartAreaInteractive } from "./ChartAreaInteractive.vue"

// Bar
export { default as ChartBar } from "./ChartBar.vue"
export { default as ChartBarInteractive } from "./ChartBarInteractive.vue"

// Pie / Donut (ChartDonut is an alias; pass arc-width for a donut)
export { default as ChartPie } from "./ChartPie.vue"
export { default as ChartDonut } from "./ChartPie.vue"

// Composed (area + line)
export { default as ChartComposed } from "./ChartComposed.vue"

// Radar
export { default as ChartRadar } from "./ChartRadar.vue"

// Radial bar
export { default as ChartRadialBar } from "./ChartRadialBar.vue"

// Bespoke bar variants
export { default as ChartBar3D } from "./ChartBar3D.vue"
export { default as ChartBarAnimated } from "./ChartBarAnimated.vue"

export { componentToString } from "./utils"

// Format: { THEME_NAME: CSS_SELECTOR }
export const THEMES = { light: "", dark: ".dark" } as const

export type ChartConfig = {
  [k in string]: {
    label?: string | Component
    icon?: string | Component
  } & (
    | { color?: string, theme?: never }
    | { color?: never, theme: Record<keyof typeof THEMES, string> }
  )
}

interface ChartContextProps {
  id: string
  config: Ref<ChartConfig>
}

export const [useChart, provideChartContext] = createContext<ChartContextProps>("Chart")

export { VisCrosshair as ChartCrosshair, VisTooltip as ChartTooltip } from "@unovis/vue"
