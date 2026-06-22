import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart",
  title: "Chart",
  description:
    "Charting foundation built on unovis-vue. ChartContainer provides the theming, config context, tooltip wiring, and legend that every chart shares. For ready-made charts see Line, Area, Bar, Pie, and Semi Circle.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartContainer", "ChartTooltipContent", "ChartLegendContent"],
  },
  whenToUse: {
    title: "When to use?",
    description:
      "Reach for the chart-type components (Line, Area, Bar, Pie) for everyday charts and the Semi Circle gauge for progress. Use these primitives directly only when you need a custom Unovis composition; ChartContainer still gives you the shared theming and tooltip/legend plumbing.",
  },
  anatomy: {
    tree: [
      {
        component: "ChartContainer",
        children: [
          {
            component: "VisXYContainer",
            children: [
              { component: "VisArea" },
              { component: "VisLine" },
              { component: "VisAxis" },
              { component: "ChartTooltip" },
              { component: "ChartCrosshair" },
            ],
          },
          { component: "ChartLegendContent" },
        ],
      },
    ],
  },
  sections: [],
  apiReference: [
    {
      component: "ChartContainer",
      props: [
        { name: "config", type: "ChartConfig", default: "—", description: "Map of dataKey → { label, color, icon }. Drives theming and the legend." },
        { name: "cursor", type: "boolean", default: "false", description: "Show the Unovis crosshair line." },
        { name: "class", type: "string", default: "—", description: "Extra classes for the wrapping div." },
      ],
      slots: [
        { name: "default", description: "Scoped slot exposing { id, config } for the chart leaf components." },
      ],
    },
    {
      component: "ChartTooltipContent",
      props: [
        { name: "config", type: "ChartConfig", default: "—", description: "Theming map for tooltip swatches and labels." },
        { name: "indicator", type: "\"dot\" | \"line\" | \"dashed\"", default: "\"dot\"", description: "Indicator style next to each value." },
        { name: "hideLabel", type: "boolean", default: "false", description: "Hide the tooltip title row." },
        { name: "labelFormatter", type: "(d) => string", default: "—", description: "Format the title (e.g. dates)." },
      ],
    },
    {
      component: "ChartLegendContent",
      props: [
        { name: "verticalAlign", type: "\"top\" | \"bottom\"", default: "\"bottom\"", description: "Position relative to the chart." },
        { name: "hideIcon", type: "boolean", default: "false", description: "Hide per-series icons from the config." },
      ],
    },
  ],
});
