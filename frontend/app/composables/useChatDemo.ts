import { computed, ref } from "vue";

/**
 * Tiny scripted chat simulator that drives the read-only MessageScroller demo.
 * It replays a fixed transcript: sending plays the next user turn, then streams
 * the assistant reply in small chunks so the autoscroll/anchor behaviour is
 * visible. This stands in for the React demo's `useChat` + transport.
 */
export type ChatRole = "user" | "assistant";

export interface ChatMessage {
  id: string;
  role: ChatRole;
  text: string;
}

export interface ScriptTurn {
  role: ChatRole;
  text: string;
}

export type ChatStatus = "ready" | "submitted" | "streaming";

const CHUNK_DELAY_MS = 20;
const CHUNK_SIZE = 3;

export function useChatDemo(script: ScriptTurn[], seedCount = 0) {
  let uid = 0;
  const nextId = () => `m-${uid++}`;

  const buildSeed = (): ChatMessage[] =>
    script.slice(0, seedCount).map((turn) => ({
      id: nextId(),
      role: turn.role,
      text: turn.text,
    }));

  const messages = ref<ChatMessage[]>(buildSeed());
  const pointer = ref(seedCount);
  const status = ref<ChatStatus>("ready");
  let timer: ReturnType<typeof setInterval> | null = null;

  const nextTurn = computed<ScriptTurn | null>(
    () => script[pointer.value] ?? null
  );
  // The queued text shown in the composer is the next *user* turn.
  const nextMessageText = computed<string | null>(() =>
    nextTurn.value?.role === "user" ? nextTurn.value.text : null
  );
  const isBusy = computed(
    () => status.value === "submitted" || status.value === "streaming"
  );

  function clearTimer(): void {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function streamAssistant(turn: ScriptTurn): void {
    const message = ref<ChatMessage>({
      id: nextId(),
      role: "assistant",
      text: "",
    });
    messages.value = [...messages.value, message.value];
    pointer.value += 1;
    status.value = "streaming";

    let cursor = 0;
    const full = turn.text;
    timer = setInterval(() => {
      cursor = Math.min(full.length, cursor + CHUNK_SIZE);
      const head = messages.value.slice(0, -1);
      const last = messages.value[messages.value.length - 1];
      messages.value = [...head, { ...last, text: full.slice(0, cursor) }];
      if (cursor >= full.length) {
        clearTimer();
        status.value = "ready";
      }
    }, CHUNK_DELAY_MS);
  }

  function sendMessage(): void {
    if (isBusy.value) {
      return;
    }
    const turn = nextTurn.value;
    if (!turn || turn.role !== "user") {
      return;
    }
    messages.value = [
      ...messages.value,
      { id: nextId(), role: "user", text: turn.text },
    ];
    pointer.value += 1;
    status.value = "submitted";

    const reply = script[pointer.value];
    if (reply?.role === "assistant") {
      streamAssistant(reply);
    } else {
      status.value = "ready";
    }
  }

  function reset(): void {
    clearTimer();
    uid = 0;
    messages.value = buildSeed();
    pointer.value = seedCount;
    status.value = "ready";
  }

  return {
    messages,
    status,
    isBusy,
    nextMessageText,
    sendMessage,
    reset,
  };
}
