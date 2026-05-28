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
  ],
  apiReference: [
    {
      component: "Collapsible",
      props: [
        { name: "open", type: "boolean", default: "false", description: "Open state. Supports v-model:open." },
        { name: "defaultOpen", type: "boolean", default: "false", description: "Initial open state when v-model is not used." },
        { name: "disabled", type: "boolean", default: "false", description: "Prevent toggling." },
      ],
    },
  ],
});
