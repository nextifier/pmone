import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "field",
  title: "Field",
  description:
    "Wrapper for form controls. Vertical, horizontal, or responsive orientation, with companion components (FieldLabel, FieldDescription, FieldError, FieldGroup, FieldSet) for every layout need.",
  installation: {
    importPath: "@/components/ui/field",
    imports: [
      "Field",
      "FieldLabel",
      "FieldDescription",
      "FieldError",
      "FieldGroup",
      "FieldSet",
      "FieldLegend",
      "FieldSeparator",
      "FieldContent",
      "FieldTitle",
    ],
  },
  sections: [
    {
      id: "basic",
      title: "Basic",
      description: "Standard layout: FieldLabel above, control in the middle, FieldDescription below.",
      examples: ["basic"],
      align: "center",
    },
    {
      id: "with-error",
      title: "Error state",
      description:
        "Set data-invalid on Field, aria-invalid on the control, then use FieldError for the message.",
      examples: ["with-error"],
      align: "center",
    },
    {
      id: "horizontal",
      title: "Horizontal orientation",
      description: "Label and control on one row. Suits short forms and setting toggles.",
      examples: ["horizontal"],
      align: "center",
    },
    {
      id: "group",
      title: "FieldSet and FieldGroup",
      description:
        "Wrap related fields together. FieldLegend gives the section a heading; FieldGroup is a vertical container between fields.",
      examples: ["group"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Field",
      props: [
        {
          name: "orientation",
          type: '"vertical" | "horizontal" | "responsive"',
          default: '"vertical"',
          description: "Layout direction. Responsive switches to horizontal at the md breakpoint.",
        },
        {
          name: "data-invalid",
          type: "boolean",
          default: "—",
          description: "HTML attribute. Set true to apply destructive colors to label and children.",
        },
      ],
    },
    {
      component: "FieldLabel / FieldDescription / FieldError",
      props: [
        {
          name: "for",
          type: "string",
          default: "—",
          description: "On FieldLabel, same as the native HTML for attribute. Must match the control id.",
        },
      ],
    },
    {
      component: "FieldSet / FieldGroup / FieldLegend",
      props: [
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Wrapper for a group of related fields with an optional heading.",
        },
      ],
    },
  ],
});
