import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-line",
  title: "Chart Line",
  description:
    "Time-series line charts built on unovis-vue. Pass an array of rows plus a config that maps each series key to a label and color.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: [
      "ChartLineDefault",
      "ChartLine",
      "ChartLineLinear",
      "ChartLineStep",
      "ChartLineInteractive",
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "A single-series line with a natural curve, gradient-free.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "comparison",
      title: "Comparison",
      description:
        "ChartLine overlays a dashed previous-period line and a gradient area fill for at-a-glance trends.",
      examples: ["comparison"],
      align: "start",
    },
    {
      id: "linear",
      title: "Linear",
      description: "Straight segments between points (CurveType.Linear).",
      examples: ["linear"],
      align: "start",
    },
    {
      id: "step",
      title: "Step",
      description: "Stepped interpolation (CurveType.Step).",
      examples: ["step"],
      align: "start",
    },
    {
      id: "interactive",
      title: "Interactive",
      description: "Toggle between series; totals update in the header.",
      examples: ["interactive"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ChartLineDefault",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Map of dataKey → { label, color }." },
        { name: "dataKey", type: "string", default: "\"value\"", description: "Row key for the y value." },
        { name: "xKey", type: "string", default: "\"date\"", description: "Row key for the x value." },
      ],
    },
    {
      component: "ChartLine",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows (each with a date key)." },
        { name: "config", type: "ChartConfig", default: "—", description: "Theming map." },
        { name: "dataKey", type: "string", default: "\"value\"", description: "Row key for the y value." },
        { name: "gradient", type: "boolean", default: "false", description: "Render a gradient area fill under the line." },
        { name: "comparisonData", type: "Record<string, any>[]", default: "[]", description: "Previous-period rows overlaid as a dashed line." },
        { name: "comparisonLabel", type: "string", default: "\"Previous Period\"", description: "Legend/tooltip label for the comparison series." },
        { name: "yTickFormatter", type: "(d) => string", default: "null", description: "Custom y-axis tick formatter." },
      ],
    },
    {
      component: "ChartLineLinear / ChartLineStep",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Theming map." },
        { name: "dataKey", type: "string", default: "\"value\"", description: "Row key for the y value." },
        { name: "xKey", type: "string", default: "\"date\"", description: "Row key for the x value." },
      ],
    },
    {
      component: "ChartLineInteractive",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "—", description: "Series rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Theming map; series with a color become toggles." },
        { name: "dataKeys", type: "string[]", default: "null", description: "Override which series are selectable." },
        { name: "xKey", type: "string", default: "\"date\"", description: "Row key for the x value." },
        { name: "title", type: "string", default: "\"\"", description: "Optional header title." },
        { name: "description", type: "string", default: "\"\"", description: "Optional header description." },
      ],
    },
  ],
});
