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
  ],
  apiReference: [
    {
      component: "HoverCard",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
        { name: "openDelay", type: "number", default: "700", description: "Delay before opening (ms)." },
        { name: "closeDelay", type: "number", default: "300", description: "Delay before closing (ms)." },
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
});
