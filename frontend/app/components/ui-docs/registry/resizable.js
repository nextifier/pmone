import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "resizable",
  title: "Resizable",
  description:
    "Split-pane layout with drag-to-resize handles. Compose ResizablePanelGroup with ResizablePanel children and ResizableHandle between them.",
  installation: {
    importPath: "@/components/ui/resizable",
    imports: ["ResizablePanelGroup", "ResizablePanel", "ResizableHandle"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Two-pane horizontal split.",
      examples: ["default"],
      align: "start",
    },
    {
      id: "vertical",
      title: "Vertical",
      description: "Stack panels with direction=\"vertical\".",
      examples: ["vertical"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ResizablePanelGroup",
      props: [
        { name: "direction", type: '"horizontal" | "vertical"', default: '"horizontal"', description: "Split axis." },
        { name: "autoSaveId", type: "string", default: "—", description: "Persist layout to localStorage under this key." },
      ],
    },
    {
      component: "ResizablePanel",
      props: [
        { name: "defaultSize", type: "number", default: "—", description: "Initial size percentage (0-100)." },
        { name: "minSize", type: "number", default: "—", description: "Minimum size percentage." },
        { name: "maxSize", type: "number", default: "—", description: "Maximum size percentage." },
      ],
    },
    {
      component: "ResizableHandle",
      props: [
        { name: "withHandle", type: "boolean", default: "false", description: "Render a visible drag handle marker." },
      ],
    },
  ],
});
