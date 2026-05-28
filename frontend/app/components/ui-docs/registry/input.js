import Default from "../examples/input/default.vue";
import DefaultSrc from "../examples/input/default.vue?raw";
import Disabled from "../examples/input/disabled.vue";
import DisabledSrc from "../examples/input/disabled.vue?raw";
import Types from "../examples/input/types.vue";
import TypesSrc from "../examples/input/types.vue?raw";
import WithField from "../examples/input/with-field.vue";
import WithFieldSrc from "../examples/input/with-field.vue?raw";
import WithError from "../examples/input/with-error.vue";
import WithErrorSrc from "../examples/input/with-error.vue?raw";

export default {
  name: "input",
  title: "Input",
  description:
    "Single-line text field. Supports every native HTML input type (text, email, number, date, file). Pair it with Field to add a label, description, or error message.",
  installation: {
    importPath: "@/components/ui/input",
    imports: ["Input"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Standard input bound with v-model.",
      examples: [{ component: Default, source: DefaultSrc, align: "center" }],
    },
    {
      id: "disabled",
      title: "Disabled",
      description: "The native disabled attribute works as expected.",
      examples: [{ component: Disabled, source: DisabledSrc, align: "center" }],
    },
    {
      id: "types",
      title: "Type variants",
      description: "Every native HTML type is supported through the type attribute.",
      examples: [{ component: Types, source: TypesSrc, align: "center" }],
    },
    {
      id: "with-field",
      title: "With Field",
      description:
        "Wrap Input in Field, use FieldLabel for the label, and FieldDescription for hint text.",
      examples: [{ component: WithField, source: WithFieldSrc, align: "center" }],
    },
    {
      id: "with-error",
      title: "Error state",
      description:
        "Set data-invalid on Field, aria-invalid on Input, and use FieldError for the message.",
      examples: [{ component: WithError, source: WithErrorSrc, align: "center" }],
    },
  ],
  apiReference: [
    {
      component: "Input",
      props: [
        {
          name: "modelValue",
          type: "string | number",
          default: "—",
          description: "Value. Supports v-model.",
        },
        {
          name: "defaultValue",
          type: "string | number",
          default: "—",
          description: "Starting value when v-model is not used.",
        },
        {
          name: "type",
          type: "string",
          default: '"text"',
          description: "Native HTML input type.",
        },
        {
          name: "class",
          type: "string",
          default: "—",
          description: "Extra classes, merged with cn().",
        },
      ],
    },
  ],
};
