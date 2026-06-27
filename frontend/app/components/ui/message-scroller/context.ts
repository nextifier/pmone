import type { InjectionKey, Ref } from "vue";
import { inject, onBeforeUnmount, onMounted, shallowRef } from "vue";
import type {
  MessageScrollerEngine,
  MessageScrollerScrollable,
  MessageScrollerScrollOptions,
  MessageScrollerVisibilityState,
  Store,
} from "./engine";

export const MessageScrollerContextKey: InjectionKey<MessageScrollerEngine> =
  Symbol("MessageScrollerContext");

function useEngine(): MessageScrollerEngine {
  const engine = inject(MessageScrollerContextKey, null);
  if (!engine) {
    throw new Error(
      "useMessageScroller must be used within a MessageScrollerProvider."
    );
  }
  return engine;
}

/**
 * Subscribe to an engine store and expose its snapshot as a reactive ref,
 * mirroring React's `useSyncExternalStore`. `onFirst`/`onLast` let lazy work
 * (the visibility IntersectionObserver) start only while something listens.
 */
function useStore<T>(
  store: Store<T>,
  onFirst?: () => void,
  onLast?: () => void
): Ref<T> {
  const state = shallowRef(store.getSnapshot()) as Ref<T>;
  let unsubscribe: (() => void) | undefined;
  // Subscribe after mount: `onFirst` (the visibility IntersectionObserver) must
  // run once the viewport and items exist, matching React effect timing.
  onMounted(() => {
    unsubscribe = store.subscribe(
      () => {
        state.value = store.getSnapshot();
      },
      onFirst,
      onLast
    );
    state.value = store.getSnapshot();
  });
  onBeforeUnmount(() => unsubscribe?.());
  return state;
}

export function useMessageScroller(): {
  scrollToEnd: (options?: MessageScrollerScrollOptions) => boolean;
  scrollToMessage: (
    messageId: string,
    options?: MessageScrollerScrollOptions
  ) => boolean;
  scrollToStart: (options?: MessageScrollerScrollOptions) => boolean;
} {
  const engine = useEngine();
  return {
    scrollToEnd: engine.scrollToEnd,
    scrollToMessage: engine.scrollToMessage,
    scrollToStart: engine.scrollToStart,
  };
}

export function useMessageScrollerScrollable(): Ref<MessageScrollerScrollable> {
  const engine = useEngine();
  return useStore(engine.stateStore);
}

export function useMessageScrollerVisibility(): Ref<MessageScrollerVisibilityState> {
  const engine = useEngine();
  return useStore(
    engine.visibilityStore,
    engine.observeVisibility,
    engine.unobserveVisibility
  );
}

export { useEngine as useMessageScrollerEngine };
