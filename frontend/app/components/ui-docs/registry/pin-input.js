import { defineComponentDoc } from "./define";

export default defineComponentDoc({
  name: "pin-input",
  title: "Pin Input",
  description:
    "Per-character input for short codes. Similar to InputOTP; PinInput is the more general reka-ui primitive (any pattern, any length), InputOTP is the OTP-tuned wrapper with paste support and complete event.",
  installation: {
    importPath: "@/components/ui/pin-input",
    imports: ["PinInput", "PinInputGroup", "PinInputSlot", "PinInputSeparator"],
  },
  sections: [
    {
      id: "default",
      title: "Default",
      description: "Four-digit pin.",
      examples: ["default"],
      align: "center",
    },
    {
      id: "with-separator",
      title: "With separator",
      description: "Split slots into groups with PinInputSeparator.",
      examples: ["with-separator"],
      align: "center",
    },
    {
      id: "mask",
      title: "Mask",
      description: "Pass mask to hide entered characters.",
      examples: ["mask"],
      align: "center",
    },
  ],
  apiReference: [
    {
      component: "PinInput",
      props: [
        { name: "modelValue", type: "string[]", default: "[]", description: "Pin value as an array of single characters. Supports v-model." },
        { name: "type", type: '"text" | "number" | "password"', default: '"text"', description: "Input type for each slot." },
        { name: "mask", type: "boolean", default: "false", description: "Mask entered values (show dots)." },
      ],
      events: [
        { name: "update:modelValue", description: "Fires as characters are entered. Enables v-model." },
        { name: "complete", description: "Fires when every slot is filled." },
      ],
    },
    {
      component: "PinInputSlot",
      props: [
        { name: "index", type: "number", default: "—", description: "Position of this slot (0-based). Forwards to reka-ui PinInputInput." },
      ],
    },
    {
      component: "PinInputGroup / PinInputSeparator",
      props: [
        { name: "class", type: "string", default: "—", description: "Group wraps a run of slots; Separator draws a divider (default slot overrides the dash)." },
      ],
    },
  ],
});
