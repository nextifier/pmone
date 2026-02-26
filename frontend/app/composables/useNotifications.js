export const useNotifications = () => {
  const client = useSanctumClient();

  const notifications = ref([]);
  const unreadCount = ref(0);
  const loading = ref(false);
  const meta = ref(null);
  const activeTab = ref("unread");

  let pollingInterval = null;

  const fetchNotifications = async (filter = activeTab.value) => {
    loading.value = true;
    try {
      const params = filter === "unread" ? "?filter=unread" : "";
      const data = await client(`/api/notifications${params}`);
      notifications.value = data.data;
      meta.value = data.meta;
    } catch (e) {
      console.error("Failed to fetch notifications", e);
    } finally {
      loading.value = false;
    }
  };

  const fetchUnreadCount = async () => {
    try {
      const data = await client("/api/notifications/unread-count");
      unreadCount.value = data.data.unread_count;
    } catch (e) {
      // Silently fail for polling
    }
  };

  const setTab = (tab) => {
    activeTab.value = tab;
    fetchNotifications(tab);
  };

  const markAsRead = async (id) => {
    // Optimistic update
    const notification = notifications.value.find((n) => n.id === id);
    if (notification && !notification.read_at) {
      notification.read_at = new Date().toISOString();
      unreadCount.value = Math.max(0, unreadCount.value - 1);
    }

    try {
      await client(`/api/notifications/${id}/mark-read`, { method: "POST" });
    } catch (e) {
      // Revert on failure
      if (notification) {
        notification.read_at = null;
        unreadCount.value += 1;
      }
    }
  };

  const markAllAsRead = async () => {
    // Optimistic update
    const previousUnread = unreadCount.value;
    const previousNotifications = notifications.value.map((n) => ({
      ...n,
    }));

    notifications.value.forEach((n) => {
      if (!n.read_at) {
        n.read_at = new Date().toISOString();
      }
    });
    unreadCount.value = 0;

    try {
      await client("/api/notifications/mark-all-read", { method: "POST" });
    } catch (e) {
      // Revert on failure
      notifications.value = previousNotifications;
      unreadCount.value = previousUnread;
    }
  };

  const startPolling = () => {
    fetchUnreadCount();
    pollingInterval = setInterval(fetchUnreadCount, 60000);
  };

  const stopPolling = () => {
    if (pollingInterval) {
      clearInterval(pollingInterval);
      pollingInterval = null;
    }
  };

  return {
    notifications,
    unreadCount,
    loading,
    meta,
    activeTab,
    fetchNotifications,
    fetchUnreadCount,
    setTab,
    markAsRead,
    markAllAsRead,
    startPolling,
    stopPolling,
  };
};
