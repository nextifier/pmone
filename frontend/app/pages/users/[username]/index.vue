<script setup>
await navigateTo({
  path: `/${useRoute().params.username}`,
});
</script>
