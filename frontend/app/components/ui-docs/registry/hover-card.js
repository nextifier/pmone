import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "hover-card",
  title: "Hover Card",
  description:
    "Floating panel that opens on hover with a small delay. Good for previews and inline context that should not require a click.",
  installation: {
    importPath: "@/components/ui/hover-card",
    imports: ["HoverCard", "HoverCardTrigger", "HoverCardContent"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Hover the trigger to reveal the card.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "profile",
      title: "Profile",
      description: "Show an avatar, name, and bio in a richer card layout.",
      examples: ["profile"],
      align: "center",
    },
    {
      id: "sides",
      title: "Sides",
      description: "Set side to top, right, bottom, or left on the content.",
      examples: ["sides"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "HoverCard",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "openDelay", type: "number", default: "700", description: "Delay before opening (ms)." },
        { name: "closeDelay", type: "number", default: "300", description: "Delay before closing (ms)." },
      ],
      events: [
        { name: "update:open", description: "Fires when the card opens or closes. Enables v-model:open." },
      ],
    },
    {
      component: "HoverCardTrigger",
      props: [
        { name: "asChild", type: "boolean", default: "false", description: "Merge props onto the child element instead of a wrapper." },
      ],
    },
    {
      component: "HoverCardContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"bottom"', description: "Anchor side." },
        { name: "align", type: '"start" | "center" | "end"', default: '"center"', description: "Alignment along the side." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moving focus onto the trigger opens the card after openDelay." },
      { keys: ["Esc"], description: "Dismisses the card while it is open." },
    ],
    notes: [
      "Intended as a sighted-user hover preview; it opens on hover or focus of the trigger.",
      "The content is not focus-trapped and the card is non-modal.",
    ],
  },
});
