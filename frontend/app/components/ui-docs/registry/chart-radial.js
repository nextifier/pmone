import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-radial",
  title: "Chart Radial Bar",
  description:
    "Radial bar charts render each value as a concentric arc. Built as a self-contained SVG component with gradient bars, a glow, background tracks, and inline labels.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartRadialBar"],
  },
  sections: [
    {
      id: "labeled",
      title: "Labeled",
      description: "Concentric bars with inline labels, gradient fills, and a legend.",
      examples: ["labeled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ChartRadialBar",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "One row per ring." },
        { name: "config", type: "ChartConfig", default: "—", description: "Map of name → { label, color }." },
        { name: "valueKey", type: "string", default: "\"value\"", description: "Row key for the bar value." },
        { name: "nameKey", type: "string", default: "\"name\"", description: "Row key matched against config." },
        { name: "innerRadius", type: "number", default: "35", description: "Inner radius of the band." },
        { name: "outerRadius", type: "number", default: "110", description: "Outer radius of the band." },
        { name: "barSize", type: "number", default: "22", description: "Thickness of each bar." },
        { name: "max", type: "number", default: "100", description: "Value mapped to a full sweep." },
        { name: "gradient", type: "boolean", default: "true", description: "Fill bars with a gradient." },
        { name: "glow", type: "boolean", default: "true", description: "Apply a glow filter." },
        { name: "background", type: "boolean", default: "true", description: "Render background tracks." },
        { name: "showLabels", type: "boolean", default: "true", description: "Render inline labels." },
        { name: "legend", type: "boolean", default: "true", description: "Render a legend below." },
      ],
    },
  ],
});
