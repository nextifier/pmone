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
      id: "icons",
      title: "With icons",
      description: "Drop an Icon inside each trigger alongside the label.",
      examples: ["icons"],
      align: "start",
    },
    {
      id: "disabled",
      title: "Disabled trigger",
      description: "Add disabled to a TabsTrigger to block it.",
      examples: ["disabled"],
      align: "start",
    },
    {
      id: "scrollable",
      title: "Overflow scroll",
      description: "When triggers exceed the width, the list scrolls horizontally instead of wrapping.",
      examples: ["scrollable"],
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
      events: [
        { name: "update:modelValue", description: "Fires when the active tab changes. Enables v-model." },
      ],
    },
    {
      component: "TabsList",
      props: [
        { name: "class", type: "string", default: "—", description: "Row of triggers. Must contain a TabsIndicator for the sliding marker." },
      ],
    },
    {
      component: "TabsTrigger",
      props: [
        { name: "value", type: "string", default: "—", description: "Identifier matched against the active tab value." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable this trigger." },
      ],
    },
    {
      component: "TabsContent / TabsIndicator",
      props: [
        { name: "value", type: "string", default: "—", description: "(TabsContent) Panel shown for the matching trigger. TabsIndicator renders the sliding marker." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["←"], description: "Moves to the previous tab (horizontal orientation)." },
      { keys: ["→"], description: "Moves to the next tab (horizontal orientation)." },
      { keys: ["↑"], description: "Moves to the previous tab (vertical orientation)." },
      { keys: ["↓"], description: "Moves to the next tab (vertical orientation)." },
      { keys: ["Home"], description: "Moves to the first tab." },
      { keys: ["End"], description: "Moves to the last tab." },
      { keys: ["Enter"], description: "Activates the focused tab (manual activation)." },
      { keys: ["Space"], description: "Activates the focused tab (manual activation)." },
    ],
    notes: [
      "Uses a roving tabindex; only the active tab is in the tab sequence.",
      "Applies tablist, tab, and tabpanel roles with aria-selected and aria-controls.",
      "Tabs activate automatically on focus by default.",
    ],
  },
});
