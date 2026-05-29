import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "form",
  title: "Form",
  description:
    "vee-validate form primitives wrapped to match the design system. Use FormField + FormItem + FormControl + FormLabel + FormMessage to build accessible forms with built-in validation rendering.",
  installation: {
    importPath: "@/components/ui/form",
    imports: ["Form", "FormField", "FormFieldArray", "FormItem", "FormLabel", "FormControl", "FormDescription", "FormMessage"],
  },
  whenToUse: {
    title: "When to use Form vs Field",
    description:
      "Use Form (vee-validate) when you want a validation schema, async checks, and field-level error handling. Use the simpler Field primitive for layout-only wrappers without validation infrastructure.",
  },
  anatomy: {
    tree: [
      { component: "Form", children: [
        { component: "FormField", children: [
          { component: "FormItem", children: [
            { component: "FormLabel" },
            { component: "FormControl" },
            { component: "FormDescription" },
            { component: "FormMessage" },
          ]},
        ]},
      ]},
    ],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single FormField bound through vee-validate.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "multiple-fields",
      title: "Multiple fields",
      description: "Several FormFields stacked inside one Form, each with its own state.",
      examples: ["multiple-fields"],
      align: "center",
    },
    {
      id: "with-validation",
      title: "With validation",
      description: "Per-field rules surface errors through FormMessage on submit.",
      examples: ["with-validation"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "Form",
      props: [
        { name: "validation-schema", type: "object", default: "—", description: "vee-validate / zod / yup schema." },
        { name: "initialValues", type: "object", default: "—", description: "Starting form values." },
      ],
    },
    {
      component: "FormField",
      props: [
        { name: "name", type: "string", default: "—", description: "Field name (path) in the form state." },
        { name: "rules", type: "object | function", default: "—", description: "Per-field validation." },
      ],
      slots: [
        { name: "default", description: "Scoped slot providing { componentField, errorMessage, value, handleChange }." },
      ],
    },
    {
      component: "FormFieldArray",
      props: [
        { name: "name", type: "string", default: "—", description: "Path to an array field in the form state." },
      ],
      slots: [
        { name: "default", description: "Scoped slot providing { fields, push, remove, swap, ... } for repeatable rows." },
      ],
    },
    {
      component: "FormItem",
      props: [
        { name: "class", type: "string", default: "—", description: "Wraps one field; provides an id via context so Label and Control link up automatically." },
      ],
    },
    {
      component: "FormLabel",
      props: [
        { name: "class", type: "string", default: "—", description: "Label bound to the FormItem control. Turns destructive when the field has an error." },
      ],
    },
    {
      component: "FormControl",
      slots: [
        { name: "default", description: "The input element. FormControl wires its id, aria-describedby, and aria-invalid from the field state." },
      ],
    },
    {
      component: "FormDescription / FormMessage",
      props: [
        { name: "class", type: "string", default: "—", description: "Description is helper text; FormMessage renders the current validation error for the field." },
      ],
    },
  ],
});
