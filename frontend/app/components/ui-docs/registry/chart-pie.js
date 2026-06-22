import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-pie",
  title: "Pie Chart",
  description:
    "Pie and donut charts built on unovis-vue. One ChartPie renders a full pie, a donut, a donut with a centre total, or nested rings.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartPie", "ChartDonut"],
  },
  sections: [
    {
      id: "pie",
      title: "Pie",
      description: "A full pie (arc-width 0). Each row maps to a config entry for its color.",
      examples: ["pie"],
      align: "center",
    },
    {
      id: "donut",
      title: "Donut",
      description: "Set arc-width to leave a hole in the centre.",
      examples: ["donut"],
      align: "center",
    },
    {
      id: "donut-text",
      title: "Donut with text",
      description: "Show a total (or any label) in the centre.",
      examples: ["donut-text"],
      align: "center",
    },
    {
      id: "stacked",
      title: "Stacked",
      description: "Concentric rings via the layers prop.",
      examples: ["stacked"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ChartPie",
      props: [
        { name: "data", type: "Record<string, any>[]", default: "[]", description: "Segment rows." },
        { name: "config", type: "ChartConfig", default: "—", description: "Map of segment name → { label, color }, plus a value-key meta entry for the tooltip label." },
        { name: "valueKey", type: "string", default: "\"value\"", description: "Row key for the segment value." },
        { name: "nameKey", type: "string", default: "\"name\"", description: "Row key matched against config for the color." },
        { name: "arcWidth", type: "number", default: "0", description: "0 = full pie; a positive value = donut ring width." },
        { name: "centerLabel", type: "string", default: "null", description: "Explicit centre label." },
        { name: "centerSubLabel", type: "string", default: "null", description: "Centre sub-label; auto-sums the value when no centerLabel/total is given." },
        { name: "total", type: "number | string", default: "null", description: "Centre total override." },
        { name: "layers", type: "{ data, valueKey, arcWidth, radius }[]", default: "null", description: "Concentric rings for a nested/stacked pie." },
        { name: "legend", type: "boolean", default: "false", description: "Render a legend below the chart." },
      ],
    },
  ],
});
