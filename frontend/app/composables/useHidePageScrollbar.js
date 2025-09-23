export default function () {
  onMounted(() => {
    document.querySelector("html").classList.add("no-scrollbar");
  });

  onUnmounted(() => {
    document.querySelector("html").classList.remove("no-scrollbar");
  });
}
