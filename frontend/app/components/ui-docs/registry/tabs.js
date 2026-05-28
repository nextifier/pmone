import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "tabs",
  title: "Tabs",
  description:
    "View switcher with three visual variants (pill, segmented, underline) and three sizes. Optional swipe gesture on mobile and automatic horizontal overflow scroll.",
  installation: {
    importPath: "@/components/ui/tabs",
    imports: ["Tabs", "TabsList", "TabsTrigger", "TabsContent", "TabsIndicator"],
  },
  sections: [
    {
      id: "variants",
      title: "Variants",
      description:
        "Pill for compact pickers in a toolbar, segmented for equivalent groupings, underline for longer section navigation.",
      examples: ["variants"],
      align: "start",
    },
    {
      id: "sizes",
      title: "Sizes",
      description: "sm (h-7), md (h-8, default), lg (h-10).",
      examples: ["sizes"],
      align: "start",
    },
    {
      id: "controlled",
      title: "Controlled",
      description: "Drive the active tab from outside with v-model.",
      examples: ["controlled"],
      align: "start",
    },
    {
      id: "swipe",
      title: "Swipe (mobile)",
      description:
        "swipe enables left/right gestures to change tabs. Carousels, tables, and nested tablists are excluded automatically.",
      examples: ["swipe"],
      align: "start",
    },
  ],
  apiReference: [
    {
      component: "Tabs",
      props: [
        {
          name: "modelValue",
          type: "string",
          default: "—",
          description: "Active tab value. Supports v-model.",
        },
        {
          name: "defaultValue",
          type: "string",
          default: "—",
          description: "Starting active tab when v-model is not used.",
        },
        {
          name: "variant",
          type: '"pill" | "segmented" | "underline"',
          default: '"pill"',
          description: "Visual style of the tab list and triggers.",
        },
        {
          name: "size",
          type: '"sm" | "md" | "lg"',
          default: '"md"',
          description: "Trigger height.",
        },
        {
          name: "swipe",
          type: "boolean",
          default: "false",
          description: "Enable left/right swipe to change tabs on mobile.",
        },
        {
          name: "swipeExclude",
          type: "string[]",
          default: '["[aria-roledescription=carousel]", ".pswp", ...]',
          description: "CSS selectors excluded from the swipe gesture.",
        },
      ],
    },
  ],
});
