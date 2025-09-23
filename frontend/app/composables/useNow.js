const now = ref(new Date());

let interval;
let activeInstances = 0;

export function useNow() {
  onMounted(() => {
    if (activeInstances === 0) {
      interval = setInterval(() => {
        now.value = new Date();
      }, 1000);
    }
    activeInstances++;
  });

  onUnmounted(() => {
    activeInstances--;
    if (activeInstances === 0) {
      clearInterval(interval);
    }
  });

  return { now };
}
