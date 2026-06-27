<script setup lang="ts">
import { ref } from "vue";
import {
  MessageScroller,
  MessageScrollerButton,
  MessageScrollerContent,
  MessageScrollerItem,
  MessageScrollerViewport,
  useMessageScroller,
  useMessageScrollerVisibility,
} from "@/components/ui/message-scroller";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

interface Item {
  id: string;
  text: string;
  anchor: boolean;
}

const items = ref<Item[]>(
  Array.from({ length: 20 }, (_, i) => ({
    id: `p${i}`,
    text: `Message ${i + 1}`,
    anchor: i % 5 === 0,
  }))
);

let olderBatch = 0;

const { scrollToMessage, scrollToStart, scrollToEnd } = useMessageScroller();
const visibility = useMessageScrollerVisibility();

function loadOlder(): void {
  olderBatch += 1;
  const batch: Item[] = Array.from({ length: 5 }, (_, i) => ({
    id: `older-${olderBatch}-${i}`,
    text: `Older ${olderBatch}.${i + 1}`,
    anchor: false,
  }));
  items.value = [...batch, ...items.value];
}

function jumpToTen(): void {
  scrollToMessage("p9", { align: "start" });
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
      <Button size="sm" variant="outline" @click="loadOlder">Load older</Button>
      <Button size="sm" variant="outline" @click="scrollToStart()">Top</Button>
      <Button size="sm" variant="outline" @click="jumpToTen">Jump to #10</Button>
      <Button size="sm" variant="outline" @click="scrollToEnd()">Bottom</Button>
      <Badge variant="muted" plain>
        visible {{ visibility.visibleMessageIds.length }}
      </Badge>
      <Badge variant="muted" plain>
        anchor {{ visibility.currentAnchorId ?? "-" }}
      </Badge>
    </div>

    <div class="h-72 overflow-hidden rounded-xl border">
      <MessageScroller>
        <MessageScrollerViewport>
          <MessageScrollerContent class="gap-2 p-3">
            <MessageScrollerItem
              v-for="item in items"
              :key="item.id"
              :message-id="item.id"
              :scroll-anchor="item.anchor"
            >
              <div
                class="rounded-lg border bg-card px-3 py-2 text-sm tracking-tight"
                :class="item.anchor ? 'border-primary/30' : ''"
              >
                {{ item.text }}
                <span v-if="item.anchor" class="text-muted-foreground"> · anchor</span>
              </div>
            </MessageScrollerItem>
          </MessageScrollerContent>
        </MessageScrollerViewport>
        <MessageScrollerButton />
      </MessageScroller>
    </div>
  </div>
</template>
