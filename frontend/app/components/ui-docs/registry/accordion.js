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
      ],
    },
    {
      component: "AccordionItem",
      props: [
        { name: "value", type: "string", default: "—", description: "Unique value to track open state." },
        { name: "disabled", type: "boolean", default: "false", description: "Disable this item." },
      ],
    },
  ],
});
