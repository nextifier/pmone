import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "circular-progress",
  title: "Circular Progress",
  description:
    "Radial progress ring (0-100) with an optional centered percentage label. The ring colour shifts by threshold: red below 34, amber up to 66, green at 67 and above.",
  installation: {
    importPath: "@/components/ui/circular-progress",
    imports: ["CircularProgress"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Pass a value between 0 and 100. The percentage renders in the center.",
      examples: ["default"],
    },
    {
      id: "thresholds",
      title: "Colour thresholds",
      description: "The ring colour reflects completeness — red (<34), amber (34-66), green (≥67).",
      examples: ["thresholds"],
    },
    {
      id: "sizes",
      title: "Sizes",
      description: "Control the diameter with size and the ring thickness with strokeWidth.",
      examples: ["sizes"],
    },
    {
      id: "without-label",
      title: "Without label",
      description: "Set showLabel to false to render the ring only — useful in tight cells.",
      examples: ["without-label"],
    },
    {
      id: "animated",
      title: "Animated",
      description: "Bind value to a ref; the ring tweens smoothly as the number changes.",
      examples: ["animated"],
    },
  ],
  apiReference: [
    {
      component: "CircularProgress",
      props: [
        { name: "value", type: "number", default: "0", description: "Progress percentage, 0-100. Values are clamped and rounded." },
        { name: "size", type: "number", default: "36", description: "Diameter of the ring in pixels." },
        { name: "strokeWidth", type: "number", default: "4", description: "Thickness of the ring stroke in pixels." },
        { name: "showLabel", type: "boolean", default: "true", description: "Show the rounded percentage in the center." },
      ],
    },
  ],
});
