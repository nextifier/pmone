export function useInboxHelpers() {
  const { getCountryFromPhone } = usePhoneCountry();

  const statusConfigs = {
    new: {
      label: "New",
      color: "bg-blue-500/10 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400",
    },
    in_progress: {
      label: "In Progress",
      color: "bg-yellow-500/10 text-yellow-600 dark:bg-yellow-500/20 dark:text-yellow-400",
    },
    completed: {
      label: "Completed",
      color: "bg-green-500/10 text-green-600 dark:bg-green-500/20 dark:text-green-400",
    },
    archived: {
      label: "Archived",
      color: "bg-gray-500/10 text-gray-600 dark:bg-gray-500/20 dark:text-gray-400",
    },
  };

  function getStatusConfig(status: string) {
    return statusConfigs[status as keyof typeof statusConfigs] || statusConfigs.new;
  }

  function formatFieldLabel(key: string) {
    return key.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
  }

  function formatWhatsAppNumber(phone: string) {
    let cleaned = phone.replace(/[^\d+]/g, "");
    cleaned = cleaned.replace(/^\+/, "");
    if (cleaned.startsWith("0")) {
      cleaned = "62" + cleaned.substring(1);
    }
    return cleaned;
  }

  return {
    getStatusConfig,
    formatFieldLabel,
    formatWhatsAppNumber,
    getCountryFromPhone,
  };
}
