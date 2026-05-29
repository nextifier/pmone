import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "tooltip",
  title: "Tooltip",
  description:
    "Small floating label that appears on hover or focus. Use for icon button names and brief shortcut hints.",
  installation: {
    importPath: "@/components/ui/tooltip",
    imports: ["TooltipProvider", "Tooltip", "TooltipTrigger", "TooltipContent"],
  },
  sections: [
    { id: "default", title: "Default", description: "Wrap an element in TooltipTrigger.", examples: ["default"], align: "center" },
    { id: "sides", title: "Sides", description: "Anchor on top / right / bottom / left.", examples: ["sides"], align: "center" },
  ],
  apiReference: [
    {
      component: "TooltipProvider",
      props: [
        { name: "delayDuration", type: "number", default: "700", description: "Hover delay before showing." },
        { name: "skipDelayDuration", type: "number", default: "300", description: "Window during which the next tooltip skips the delay." },
      ],
    },
    {
      component: "Tooltip",
      props: [
        { name: "open", type: "boolean", default: "—", description: "Open state. Supports v-model:open." },
      ],
      events: [
        { name: "update:open", description: "Fires when the tooltip shows or hides. Enables v-model:open." },
      ],
    },
    {
      component: "TooltipTrigger",
      props: [
        { name: "asChild", type: "boolean", default: "false", description: "Merge props onto the child element instead of a wrapper." },
      ],
    },
    {
      component: "TooltipContent",
      props: [
        { name: "side", type: '"top" | "right" | "bottom" | "left"', default: '"top"', description: "Anchor side." },
        { name: "sideOffset", type: "number", default: "4", description: "Gap from the trigger in px." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moving focus onto the trigger shows the tooltip." },
      { keys: ["Esc"], description: "Dismisses the tooltip while it is open." },
    ],
    notes: [
      "Opens on keyboard focus, not only on pointer hover, so keyboard users get the same hint.",
      "The tooltip content is non-focusable and is linked to the trigger via aria-describedby.",
    ],
  },
});
