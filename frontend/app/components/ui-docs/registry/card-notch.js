import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "card-notch",
  title: "Card Notch",
  description:
    "Card with a circular notch cut into one of its corners or edges. The notch can hold an icon, avatar, or short label that floats above the surface.",
  installation: {
    importPath: "@/components/ui/card-notch",
    imports: ["CardNotch"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Top-right notch with an icon.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "positions",
      title: "Positions",
      description: "Six positions: top-left/center/right, bottom-left/center/right.",
      examples: ["positions"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "CardNotch",
      props: [
        {
          name: "position",
          type: '"top-left" | "top-center" | "top-right" | "bottom-left" | "bottom-center" | "bottom-right"',
          default: '"top-right"',
          description: "Where to cut the notch.",
        },
        { name: "notchSize", type: "number", default: "48", description: "Notch diameter in px." },
        { name: "notchGap", type: "number", default: "8", description: "Gap between notch and card edge in px." },
        { name: "cardBg", type: "string", default: '"var(--card)"', description: "Card background fill colour for the SVG mask." },
        { name: "bordered", type: "boolean", default: "false", description: "Stroke the path." },
        { name: "borderColor", type: "string", default: '"var(--border)"', description: "Border colour." },
        { name: "bodyClass", type: "string", default: "—", description: "Extra classes for the inner body div." },
        { name: "to", type: "RouteLocationRaw", default: "—", description: "Render as NuxtLink." },
      ],
      slots: [
        { name: "default", description: "Card body content." },
        { name: "notch", description: "Content inside the notch (icon/avatar/text)." },
      ],
    },
  ],
});
