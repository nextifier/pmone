import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "scroll-spy",
  title: "Scroll Spy",
  description:
    "Auto-generated \"On this page\" sidebar that tracks the currently visible heading. Powers the right rail of every UI Library doc page.",
  installation: {
    importPath: "@/components/ui/scroll-spy",
    imports: ["ScrollSpy"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Point it at a container; ScrollSpy finds h2-h6 inside and renders a sticky nav.",
      examples: ["default"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "ScrollSpy",
      props: [
        { name: "contentSelector", type: "string", default: "—", description: "CSS selector for the container ScrollSpy walks for headings." },
        { name: "excludeSelector", type: "string", default: '""', description: "Skip headings whose closest match this selector (e.g. \"[role=tabpanel]\")." },
        { name: "showLabel", type: "boolean", default: "true", description: "Show the 'On This Page' label above the nav." },
      ],
      events: [
        { name: "headings-found", description: "Emitted on mount with the resolved headings array." },
      ],
    },
  ],
});
