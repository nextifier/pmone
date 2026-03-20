interface Conversation {
  id: string;
  title: string;
  created_at: string;
  updated_at: string;
}

interface Message {
  id: string;
  role: "user" | "assistant";
  content: string;
  created_at: string;
}

export function useAiChat() {
  const client = useSanctumClient();
  const apiUrl = useRuntimeConfig().public.apiUrl as string;
  const { fetchUsage } = useAiUsage();

  const conversations = useState<Conversation[]>("ai-conversations", () => []);
  const activeConversationId = useState<string | null>("ai-active-conversation-id", () => null);
  const messages = useState<Message[]>("ai-messages", () => []);
  const isStreaming = useState("ai-is-streaming", () => false);
  const streamingContent = useState("ai-streaming-content", () => "");
  const toolStatus = useState<string | null>("ai-tool-status", () => null);
  const isLoadingConversations = useState("ai-loading-conversations", () => false);
  const hasFetchedConversations = useState("ai-has-fetched-conversations", () => false);
  const isLoadingMessages = useState("ai-loading-messages", () => false);

  let abortController: AbortController | null = null;

  const activeConversation = computed(() =>
    conversations.value.find((c) => c.id === activeConversationId.value)
  );

  async function fetchConversations() {
    isLoadingConversations.value = true;
    try {
      const res = await client("/api/ai/conversations");
      conversations.value = res.data;
    } finally {
      isLoadingConversations.value = false;
      hasFetchedConversations.value = true;
    }
  }

  async function loadConversation(conversationId: string) {
    activeConversationId.value = conversationId;
    isLoadingMessages.value = true;
    streamingContent.value = "";
    try {
      const res = await client(
        `/api/ai/conversations/${conversationId}/messages`
      );
      messages.value = res.data;
    } finally {
      isLoadingMessages.value = false;
    }
  }

  async function sendMessage(content: string) {
    if (isStreaming.value) return;

    // Add user message to UI immediately
    const userMessage: Message = {
      id: `temp-${Date.now()}`,
      role: "user",
      content,
      created_at: new Date().toISOString(),
    };
    messages.value.push(userMessage);

    isStreaming.value = true;
    streamingContent.value = "";
    toolStatus.value = null;
    abortController = new AbortController();

    try {
      // Get CSRF token from cookie
      const xsrfToken = useCookie("XSRF-TOKEN").value;

      const response = await fetch(`${apiUrl}/api/ai/chat`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "text/event-stream",
          ...(xsrfToken ? { "X-XSRF-TOKEN": xsrfToken } : {}),
        },
        credentials: "include",
        signal: abortController.signal,
        body: JSON.stringify({
          message: content,
          conversation_id: activeConversationId.value,
        }),
      });

      if (!response.ok) {
        const errorText = await response.text();
        let errorMessage = "Failed to send message.";
        try {
          const errorJson = JSON.parse(errorText);
          errorMessage = errorJson.message || errorMessage;
        } catch {
          // use default
        }
        throw new Error(errorMessage);
      }

      const reader = response.body!.getReader();
      const decoder = new TextDecoder();
      let buffer = "";

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        const lines = buffer.split("\n");
        buffer = lines.pop() || "";

        for (const line of lines) {
          if (!line.startsWith("data: ")) continue;
          const dataStr = line.slice(6).trim();
          if (!dataStr) continue;

          let data;
          try {
            data = JSON.parse(dataStr);
          } catch {
            // Skip unparseable lines
            continue;
          }

          if (data.type === "error") {
            throw new Error(data.message || "Terjadi kesalahan.");
          } else if (data.type === "text_delta") {
            streamingContent.value += data.delta;
            toolStatus.value = null;
          } else if (data.type === "tool_call") {
            toolStatus.value = `Querying database...`;
          } else if (data.type === "tool_result") {
            toolStatus.value = null;
          } else if (data.type === "done") {
            if (data.conversation_id) {
              const isNewConversation = !activeConversationId.value;
              activeConversationId.value = data.conversation_id;

              if (isNewConversation) {
                // Refresh conversation list to get the new one
                await fetchConversations();
              }
            }
          }
        }
      }

      // Push the completed assistant message
      if (streamingContent.value) {
        const assistantMessage: Message = {
          id: `temp-${Date.now()}-assistant`,
          role: "assistant",
          content: streamingContent.value,
          created_at: new Date().toISOString(),
        };
        messages.value.push(assistantMessage);
      }
    } catch (error: any) {
      if (error.name === "AbortError") {
        // User stopped streaming - keep what we have
        if (streamingContent.value) {
          messages.value.push({
            id: `temp-${Date.now()}-assistant`,
            role: "assistant",
            content: streamingContent.value,
            created_at: new Date().toISOString(),
          });
        }
      } else {
        // Remove the optimistic user message on error
        messages.value.pop();
        throw error;
      }
    } finally {
      isStreaming.value = false;
      streamingContent.value = "";
      toolStatus.value = null;
      abortController = null;
      fetchUsage();
    }
  }

  function stopStreaming() {
    if (abortController) {
      abortController.abort();
    }
  }

  function newConversation() {
    activeConversationId.value = null;
    messages.value = [];
    streamingContent.value = "";
  }

  async function deleteConversation(conversationId: string) {
    await client(`/api/ai/conversations/${conversationId}`, {
      method: "DELETE",
    });

    conversations.value = conversations.value.filter(
      (c) => c.id !== conversationId
    );

    if (activeConversationId.value === conversationId) {
      newConversation();
    }
  }

  return {
    conversations,
    activeConversationId,
    activeConversation,
    messages,
    isStreaming,
    streamingContent,
    toolStatus,
    isLoadingConversations,
    hasFetchedConversations,
    isLoadingMessages,
    fetchConversations,
    loadConversation,
    sendMessage,
    stopStreaming,
    newConversation,
    deleteConversation,
  };
}
