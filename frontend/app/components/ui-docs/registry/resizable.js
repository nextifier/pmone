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
    {
      id: "with-handle",
      title: "With handle",
      description: "Nested groups with visible drag handles via withHandle on each ResizableHandle.",
      examples: ["with-handle"],
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
      events: [
        { name: "layout", description: "Fires with the array of panel sizes whenever the layout changes." },
      ],
    },
    {
      component: "ResizablePanel",
      props: [
        { name: "defaultSize", type: "number", default: "—", description: "Initial size percentage (0-100)." },
        { name: "minSize", type: "number", default: "—", description: "Minimum size percentage." },
        { name: "maxSize", type: "number", default: "—", description: "Maximum size percentage." },
        { name: "collapsible", type: "boolean", default: "false", description: "Allow the panel to collapse past minSize." },
      ],
      events: [
        { name: "resize", description: "Fires with the new size when this panel is resized." },
        { name: "collapse", description: "Fires when the panel collapses." },
        { name: "expand", description: "Fires when the panel expands from a collapsed state." },
      ],
    },
    {
      component: "ResizableHandle",
      props: [
        { name: "withHandle", type: "boolean", default: "false", description: "Render a visible drag handle marker." },
      ],
      events: [
        { name: "dragging", description: "Fires with a boolean as the handle starts and stops dragging." },
      ],
    },
  ],
});
