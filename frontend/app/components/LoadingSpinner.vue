<template>
  <div class="loader aspect-square rounded-full border-2"></div>
</template>

<style>
@keyframes loader-rotate {
  0% {
    transform: rotate(0);
  }
  100% {
    transform: rotate(360deg);
  }
}
.loader {
  border-right-color: transparent !important;
  animation: loader-rotate 1s linear infinite;
}
</style>
