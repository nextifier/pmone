<template>
  <div class="loader-container">
    <div v-for="n in 3" class="bar rounded-xs bg-red-600"></div>
  </div>
</template>

<style scoped>
.loader-container {
  --uib-size: 16px;
  --uib-speed: 1.5s;
  --uib-stroke: 4px;
  width: var(--uib-size);
  height: calc(var(--uib-size) * 0.9);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.bar {
  /* background-color: #ef4444; */
  width: var(--uib-stroke);
  height: 100%;
  transition: background-color 0.3s ease;
}

.bar:nth-child(1) {
  animation: grow var(--uib-speed) ease-in-out calc(var(--uib-speed) * -0.45)
    infinite;
}

.bar:nth-child(2) {
  animation: grow var(--uib-speed) ease-in-out calc(var(--uib-speed) * -0.3)
    infinite;
}

.bar:nth-child(3) {
  animation: grow var(--uib-speed) ease-in-out calc(var(--uib-speed) * -0.15)
    infinite;
}

.bar:nth-child(4) {
  animation: grow var(--uib-speed) ease-in-out infinite;
}

@keyframes grow {
  0%,
  100% {
    transform: scaleY(0.3);
  }

  50% {
    transform: scaleY(1);
  }
}
</style>
