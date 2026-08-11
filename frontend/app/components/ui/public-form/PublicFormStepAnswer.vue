<script setup>
import { onBeforeUnmount, ref, useId, watch } from "vue";
import { injectQuestionnaireItemContext } from "../questionnaire";

/**
 * Teaches a QuestionnaireItem that its answer lives in Vue state rather than in
 * a native input.
 *
 * Questionnaire decides whether a step is answered from the controls
 * QuestionnaireInput and QuestionnaireChoice register, and those need a real
 * HTMLInputElement. CustomFieldRenderer registers nothing, so without a bridge
 * every step reads as permanently unanswered - which does not just break the
 * Next button, it makes the submit scan reject the whole form and bounce the
 * visitor to question one.
 *
 * Registering only a *selection* is not enough: `answered` is
 * `answers.some(a => selectedAnswerIds.includes(a.id))`, and `answers` comes from
 * the control registry, so an empty registry is always false no matter what is
 * selected. Both halves have to be registered, which is why there is a proxy
 * input here at all.
 *
 * It is `type="hidden"` on purpose. focusInvalid() looks for
 * `input:not([type=hidden])`, so a hidden proxy keeps focus landing on the
 * field's own control instead of on a decoy the visitor cannot see. It also
 * never carries a `name`, so isAnswerFilled() stays false and the component's
 * native-constraint pass skips it - validation is ours, in
 * custom-field/validation.ts.
 *
 * Nothing in components/ui/questionnaire changes for any of this, which matters:
 * that folder is vendored byte-identical across three repos.
 */
const props = defineProps({
  /** True when the step should count as answered. */
  filled: { type: Boolean, required: true },
});

const item = injectQuestionnaireItemContext();

const answerId = useId();
const proxyElement = ref(null);

const unregisterSelection = item.registerAnswerSelection(answerId, props.filled);

let unregisterControl = null;

watch(
  proxyElement,
  (element) => {
    unregisterControl?.();
    unregisterControl = element
      ? item.registerAnswerControl({
          disabled: false,
          element,
          id: answerId,
          ownDisabled: false,
          type: "input",
          value: "",
        })
      : null;
  },
  { flush: "post" },
);

watch(
  () => props.filled,
  (filled) => item.syncControlledAnswerSelection(answerId, filled),
  { immediate: true },
);

onBeforeUnmount(() => {
  unregisterControl?.();
  unregisterControl = null;
  unregisterSelection();
});
</script>

<template>
  <input ref="proxyElement" type="hidden" data-slot="public-form-step-proxy" />
  <slot />
</template>
