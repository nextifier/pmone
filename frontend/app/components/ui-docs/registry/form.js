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
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Single FormField bound through vee-validate.",
      examples: ["default"],
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
  ],
});
