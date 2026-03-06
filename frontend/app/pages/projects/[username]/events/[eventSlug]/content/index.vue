<script setup>
definePageMeta({
  middleware: [
    function (to) {
      const { username, eventSlug } = to.params;
      return navigateTo(
        `/projects/${username}/events/${eventSlug}/content/rundown`,
        { replace: true }
      );
    },
  ],
});
</script>
