import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart",
  title: "Chart",
  description:
    "Chart primitives built on unovis-vue. ChartContainer wires the theming and tooltip; the leaf components (ChartLineDefault, ChartSemiCircle) render specific chart types.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: [
      "ChartContainer",
      "ChartLineDefault",
      "ChartSemiCircle",
      "ChartLegendContent",
      "ChartTooltipContent",
    ],
  },
  sections: [
    {
      id: "line",
      title: "Line",
      description: "Time-series line chart with a single data key.",
      examples: ["line"],
      align: "start",
    },
    {
      id: "semi-circle",
      title: "Semi circle",
      description: "Gauge-style chart for progress or percentages.",
      examples: ["semi-circle"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ChartContainer",
      props: [
        { name: "config", type: "ChartConfig", default: "—", description: "Map of dataKey → { label, color, icon }. Drives theming and legend." },
        { name: "class", type: "string", default: "—", description: "Extra classes for the wrapping div." },
      ],
    },
    {
      component: "ChartLineDefault",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "[]", description: "Series data." },
        { name: "dataKey", type: "string", default: "—", description: "Key in each row to read the y value from." },
        { name: "config", type: "ChartConfig", default: "—", description: "Theming map." },
      ],
    },
    {
      component: "ChartSemiCircle",
      props: [
        { name: "value", type: "number", default: "0", description: "Current value." },
        { name: "max", type: "number", default: "100", description: "Maximum value." },
        { name: "label", type: "string", default: "—", description: "Centre label." },
      ],
    },
  ],
});
