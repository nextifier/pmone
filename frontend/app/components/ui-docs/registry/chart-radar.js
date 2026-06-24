import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-radar",
  title: "Chart Radar",
  description:
    "Radar charts plot one series across several axes on a polar grid. Built as a self-contained SVG component with gradient fill, glow, and dot markers.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartRadar"],
  },
  sections: [
    {
      id: "gradient",
      title: "Gradient fill",
      description: "A radar with a vertical gradient fill and a glowing stroke.",
      examples: ["gradient"],
      align: "center",
    },
    {
      id: "filled-glow",
      title: "Filled with glow",
      description: "A diagonal gradient, thicker glowing stroke, and ring-style dots.",
      examples: ["filled-glow"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ChartRadar",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "One row per axis." },
        { name: "config", type: "ChartConfig", default: "—", description: "Map of dataKey → { label, color }." },
        { name: "dataKey", type: "string", default: "\"value\"", description: "Row key for the plotted value." },
        { name: "categoryKey", type: "string", default: "\"category\"", description: "Row key for the axis label." },
        { name: "maxDomain", type: "number", default: "100", description: "Value mapped to the outer ring." },
        { name: "gridLevels", type: "number", default: "5", description: "Concentric grid rings." },
        { name: "gradient", type: "boolean", default: "true", description: "Fill the area with a gradient." },
        { name: "gradientDirection", type: "\"vertical\" | \"diagonal\"", default: "\"vertical\"", description: "Gradient direction." },
        { name: "glow", type: "boolean", default: "true", description: "Apply a glow filter to the stroke." },
        { name: "dots", type: "boolean", default: "true", description: "Render dot markers at each vertex." },
        { name: "dotVariant", type: "\"solid\" | \"ring\"", default: "\"solid\"", description: "Filled dots or background-filled rings." },
        { name: "strokeWidth", type: "number", default: "2", description: "Series outline width." },
      ],
    },
  ],
});
