import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "accordion",
  title: "Accordion",
  description:
    "Vertically stacked panels that expand to reveal their content. Built on reka-ui. Single-open by default; switch type to multiple to allow several at once.",
  installation: {
    importPath: "@/components/ui/accordion",
    imports: ["Accordion", "AccordionItem", "AccordionTrigger", "AccordionContent"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single panel open at a time.",
      examples: ["default"],
    },
    {
      id: "multiple",
      title: "Multiple",
      description: "Allow several panels open at once with type=\"multiple\".",
      examples: ["multiple"],
    },
    {
      id: "controlled",
      title: "Controlled",
      description: "Drive the open panel from outside via v-model.",
      examples: ["controlled"],
    },
  ],
  apiReference: [
    {
      component: "Accordion",
      props: [
        {
          name: "type",
          type: '"single" | "multiple"',
          default: '"single"',
          description: "Open one panel at a time, or many.",
        },
        {
          name: "modelValue",
          type: "string | string[]",
          default: "—",
          description: "Open value(s). Supports v-model. String for single, array for multiple.",
        },
        {
          name: "defaultValue",
          type: "string | string[]",
          default: "—",
          description: "Initial open value when v-model is not used.",
        },
        {
          name: "collapsible",
          type: "boolean",
          default: "false",
          description: "Allow closing the only open panel (single mode).",
        },
        {
          name: "disabled",
          type: "boolean",
          default: "false",
          description: "Disable every item at once. Forwards to reka-ui AccordionRoot.",
        },
      ],
      events: [
        { name: "update:modelValue", description: "Fires when the open panel(s) change. Enables v-model." },
      ],
      slots: [
        { name: "default", description: "AccordionItem children." },
      ],
    },
    {
      component: "AccordionItem",
      props: [
        { name: "value", type: "string", default: "—", description: "Unique value to track open state." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable this item." },
      ],
      slots: [
        { name: "default", description: "AccordionTrigger and AccordionContent." },
      ],
    },
    {
      component: "AccordionTrigger",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn(). Forwards remaining props to reka-ui AccordionTrigger.",
        },
      ],
      slots: [
        { name: "default", description: "Trigger label content." },
        { name: "icon", description: "Override the trailing chevron. Defaults to a rotating ChevronDown." },
      ],
    },
    {
      component: "AccordionContent",
      props: [
        {
          name: "forceMount",
          type: "boolean",
          default: "false",
          description: "Keep content mounted while collapsed. Forwards to reka-ui AccordionContent.",
        },
      ],
      slots: [
        { name: "default", description: "Panel body revealed when open." },
      ],
    },
  ],
  accessibility: {
    keyboard: [
      { keys: ["Tab"], description: "Moves focus to the next trigger." },
      { keys: ["Enter"], description: "Toggles the focused panel." },
      { keys: ["Space"], description: "Toggles the focused panel." },
      { keys: ["↑"], description: "Moves focus to the previous trigger." },
      { keys: ["↓"], description: "Moves focus to the next trigger." },
      { keys: ["Home"], description: "Moves focus to the first trigger." },
      { keys: ["End"], description: "Moves focus to the last trigger." },
    ],
    notes: [
      "Built on reka-ui; each trigger controls its region via aria-controls and aria-expanded.",
      "Collapsed regions are hidden from assistive technology until expanded.",
    ],
  },
});
