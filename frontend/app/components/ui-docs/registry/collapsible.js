import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "collapsible",
  title: "Collapsible",
  description:
    "Show/hide a single block of content. A simpler primitive than Accordion when you do not need multiple panels or item-level state.",
  installation: {
    importPath: "@/components/ui/collapsible",
    imports: ["Collapsible", "CollapsibleTrigger", "CollapsibleContent"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single togglable block.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "controlled",
      title: "Controlled",
      description: "Drive open state externally with v-model:open.",
      examples: ["controlled"],
      align: "center",
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "Set disabled to lock the block so the trigger cannot toggle it.",
      examples: ["disabled"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Collapsible",
      props: [
        { name: "open", type: "boolean", default: "false", description: "Open state. Supports v-model:open." },
        { name: "defaultOpen", type: "boolean", default: "false", description: "Initial open state when v-model is not used." },
        { name: "disabled", type: "boolean", default: "false", description: "Prevent toggling." },
      ],
      events: [
        { name: "update:open", description: "Fires when the open state changes. Enables v-model:open." },
      ],
      slots: [
        { name: "default", description: "Scoped slot exposing { open } alongside the trigger and content." },
      ],
    },
    {
      component: "CollapsibleTrigger",
      props: [
        {
          name: "asChild",
          type: "boolean",
          default: "false",
          description: "Merge props onto the child element. Forwards to reka-ui CollapsibleTrigger.",
        },
      ],
      slots: [
        { name: "default", description: "The element that toggles the panel." },
      ],
    },
    {
      component: "CollapsibleContent",
      props: [
        {
          name: "forceMount",
          type: "boolean",
          default: "false",
          description: "Keep content mounted while collapsed. Forwards to reka-ui CollapsibleContent.",
        },
      ],
      slots: [
        { name: "default", description: "Content revealed when open." },
      ],
    },
  ],
});
