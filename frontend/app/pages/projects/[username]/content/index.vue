<script setup>
definePageMeta({
  middleware: [
    function (to) {
      const { username } = to.params;
      return navigateTo(`/projects/${username}/content/banners`, { replace: true });
    },
  ],
});
</script>
