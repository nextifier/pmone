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
  ],
  apiReference: [
    {
      component: "PinInput",
      props: [
        { name: "modelValue", type: "string[]", default: "[]", description: "Pin value as an array of single characters. Supports v-model." },
        { name: "type", type: '"text" | "number" | "password"', default: '"text"', description: "Input type for each slot." },
        { name: "mask", type: "boolean", default: "false", description: "Mask entered values (show dots)." },
      ],
    },
  ],
});
