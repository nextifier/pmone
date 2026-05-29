import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "card-notch",
  title: "Card Notch",
  description:
    "Card with a true transparent notched cutout where a circular element meets the card body. Uses an SVG clip-path for crisp corners. Falls back to a plain card when no notch slot is present.",
  installation: {
    importPath: "@/components/ui/card-notch",
    imports: ["CardNotch"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Notch at the bottom-right (default). Reserve clearance with body-class (e.g. pb-20).",
      examples: ["default"],
      align: "center",
    },
    {
      id: "plain",
      title: "Plain card (no notch)",
      description: "Without a #notch slot and without the notched prop, CardNotch renders a plain rounded card.",
      examples: ["plain"],
      align: "start",
    },
    {
      id: "positions",
      title: "Positions",
      description: "Six positions: top-left/center/right and bottom-left/center/right.",
      examples: ["positions"],
      align: "start",
    },
    {
      id: "auto-pad",
      title: "Auto padding",
      description: "auto-pad reserves notch clearance automatically, so you only set base padding. notch-padding tunes the breathing room.",
      examples: ["auto-pad"],
      align: "start",
    },
    {
      id: "sizes",
      title: "Custom sizes",
      description: "Adjust size, gap, and radius for different looks.",
      examples: ["sizes"],
      align: "start",
    },
    {
      id: "no-border",
      title: "Without border",
      description: "Set :bordered=\"false\" to drop the stroke. The gap is genuinely transparent, so any background shows through.",
      examples: ["no-border"],
      align: "start",
    },
    {
      id: "border",
      title: "Border width & color",
      description: "Customise the stroke via border-width and border-color.",
      examples: ["border"],
      align: "start",
    },
    {
      id: "as-link",
      title: "As link or button",
      description: "Pass to/href to render as a NuxtLink, or as=\"button\" for a clickable card. External URLs open in a new tab.",
      examples: ["as-link"],
      align: "start",
    },
    {
      id: "brand-card",
      title: "Brand card",
      description: "Realistic usage: logo notch, name, category, and metadata with auto-pad.",
      examples: ["brand-card"],
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
          default: '"bottom-right"',
          description: "Where the notch element sits.",
        },
        { name: "size", type: "string", default: '"3.5rem"', description: "Notch diameter (any CSS length)." },
        { name: "gap", type: "string", default: '"8px"', description: "Transparent gap between the notch and the card edge." },
        { name: "radius", type: "string", default: '"1.5rem"', description: "Card corner radius." },
        { name: "bordered", type: "boolean", default: "true", description: "Stroke the card outline." },
        { name: "borderColor", type: "string", default: '"var(--color-primary)"', description: "Border colour." },
        { name: "borderWidth", type: "string", default: '"1px"', description: "Border thickness." },
        { name: "cardBg", type: "string", default: '"var(--color-card)"', description: "Card fill colour for the SVG mask." },
        { name: "notched", type: "boolean", default: "false", description: "Force the cutout even without a #notch slot (e.g. for skeletons)." },
        { name: "autoPad", type: "boolean", default: "false", description: "Reserve notch clearance automatically; no manual pb-* needed." },
        { name: "notchPadding", type: "string", default: '"0.75rem"', description: "Breathing room between the notch and the content when auto-pad is on." },
        { name: "bodyClass", type: "string", default: "—", description: "Classes for the inner body div (padding, layout)." },
        { name: "to", type: "RouteLocationRaw", default: "—", description: "Render as a NuxtLink to an internal route." },
        { name: "href", type: "string", default: "—", description: "Render as an anchor; https URLs open in a new tab." },
        { name: "as", type: '"div" | "button" | "a"', default: '"div"', description: "Element to render when no to/href is set." },
      ],
      slots: [
        { name: "default", description: "Card body content." },
        { name: "notch", description: "Content inside the notch circle (icon, avatar, logo)." },
      ],
    },
  ],
});
