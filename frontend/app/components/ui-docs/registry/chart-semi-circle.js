import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "chart-semi-circle",
  title: "Chart Semi Circle",
  description:
    "A radial gauge for progress, usage, or percentages. A PM One custom component (not from shadcn-vue) built with plain SVG, an animated reveal, and a count-up centre value.",
  installation: {
    importPath: "@/components/ui/chart",
    imports: ["ChartSemiCircle"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "A value over a maximum, with a centre label and the max appended.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "gradient",
      title: "Custom gradient",
      description:
        "Pass Tailwind linear-gradient classes via the gradient prop to recolour the bars, and add a unit suffix.",
      examples: ["gradient"],
      align: "center",
    },
    {
      id: "remaining",
      title: "Remaining",
      description: "Show the remaining value (max - value) in the centre with show-remaining.",
      examples: ["remaining"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "ChartSemiCircle",
      props: [
        { name: "value", type: "number", default: "—", description: "Current value (required)." },
        { name: "max", type: "number", default: "—", description: "Maximum value (required)." },
        { name: "centerLabel", type: "string", default: "\"\"", description: "Label shown under the centre value." },
        { name: "centerValue", type: "number | string", default: "null", description: "Override the centre value (defaults to value, or remaining when showRemaining)." },
        { name: "showMax", type: "boolean", default: "false", description: "Append /max to the centre value." },
        { name: "showRemaining", type: "boolean", default: "false", description: "Use max - value as the centre value." },
        { name: "suffix", type: "string", default: "\"\"", description: "Small unit appended to the centre value (e.g. %)." },
        { name: "gradient", type: "string", default: "null", description: "Tailwind linear-gradient classes sampled to colour the bars (e.g. from-sky-500 to-fuchsia-500)." },
        { name: "colors", type: "[pos, L, C, H][]", default: "rainbow", description: "OKLCH colour stops used when no gradient is set." },
        { name: "totalBars", type: "number", default: "40", description: "Number of radial bars." },
        { name: "animateBars", type: "boolean", default: "true", description: "Reveal the bars when scrolled into view." },
        { name: "animateValue", type: "boolean", default: "true", description: "Count the centre value up on reveal." },
        { name: "compact", type: "boolean", default: "true", description: "Format numbers with compact notation (1.2K)." },
      ],
    },
  ],
});
