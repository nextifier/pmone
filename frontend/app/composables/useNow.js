import { ref } from 'vue';
import { useIntervalFn } from '@vueuse/core';

export function useNow() {
  const now = ref(new Date());

  // Use VueUse's useIntervalFn which automatically handles cleanup
  const { pause, resume, isActive } = useIntervalFn(() => {
    now.value = new Date();
  }, 1000, { immediate: true });

  return {
    now,
    pause,
    resume,
    isActive
  };
}
