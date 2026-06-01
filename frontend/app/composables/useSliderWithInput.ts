import { computed, ref } from "vue";

interface UseSliderWithInputProps {
  minValue?: number;
  maxValue?: number;
  initialValue?: number[];
  defaultValue?: number[];
}

export function useSliderWithInput({
  minValue = 0,
  maxValue = 100,
  initialValue = [minValue],
  defaultValue = [minValue],
}: UseSliderWithInputProps) {
  const sliderValues = ref<number[]>(initialValue);
  const inputValues = ref<string[]>(initialValue.map((value) => value.toString()));

  const showReset = computed(
    () =>
      sliderValues.value.length === defaultValue.length &&
      !sliderValues.value.every((value, index) => value === defaultValue[index]),
  );

  function validateAndUpdateValue(rawValue: string, index: number): void {
    if (rawValue === "" || rawValue === "-") {
      inputValues.value = inputValues.value.map((value, i) => (i === index ? "0" : value));
      sliderValues.value = sliderValues.value.map((value, i) => (i === index ? 0 : value));
      return;
    }

    const numValue = Number.parseFloat(rawValue);

    if (Number.isNaN(numValue)) {
      inputValues.value = inputValues.value.map((value, i) =>
        i === index ? (sliderValues.value[index] ?? 0).toString() : value,
      );
      return;
    }

    let clampedValue = Math.min(maxValue, Math.max(minValue, numValue));

    if (sliderValues.value.length > 1) {
      clampedValue =
        index === 0
          ? Math.min(clampedValue, sliderValues.value[1] ?? maxValue)
          : Math.max(clampedValue, sliderValues.value[0] ?? minValue);
    }

    sliderValues.value = sliderValues.value.map((value, i) => (i === index ? clampedValue : value));
    inputValues.value = inputValues.value.map((value, i) =>
      i === index ? clampedValue.toString() : value,
    );
  }

  function handleInputChange(index: number, newValue: string | number): void {
    const next = newValue.toString();
    if (next === "" || /^-?\d*\.?\d*$/.test(next)) {
      inputValues.value = inputValues.value.map((value, i) => (i === index ? next : value));
    }
  }

  function handleSliderChange(newValue?: number[]): void {
    if (!newValue) {
      return;
    }

    sliderValues.value = newValue;
    inputValues.value = newValue.map((value) => value.toString());
  }

  function resetToDefault(): void {
    sliderValues.value = [...defaultValue];
    inputValues.value = defaultValue.map((value) => value.toString());
  }

  return {
    sliderValues,
    inputValues,
    validateAndUpdateValue,
    handleInputChange,
    handleSliderChange,
    resetToDefault,
    showReset,
  };
}
