<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import Sortable from "sortablejs";
import type { Answer } from "../types.ts";
import { useI18n } from "../util.ts";

/**
 * Bring items into an order: drag (touch and mouse) or the arrow buttons
 * (keyboard, screen readers). v-model = list of answer ids.
 */
const props = defineProps<{ items: Answer[] }>();
const order = defineModel<string[]>({ required: true });
const { t } = useI18n();
const list = ref<HTMLElement | null>(null);
const live = ref("");
let sortable: Sortable | null = null;

const text = (id: string): string => props.items.find((a) => a.id === id)?.text ?? "";

function move(from: number, to: number): void {
  if (to < 0 || to >= order.value.length) {
    return;
  }
  const copy = [...order.value];
  const [id] = copy.splice(from, 1);
  copy.splice(to, 0, id);
  order.value = copy;
  live.value = t("rank_moved", text(id), to + 1);
}

onMounted(() => {
  if (!list.value) {
    return;
  }
  sortable = Sortable.create(list.value, {
    handle: ".uda-rankin__handle",
    animation: 150,
    forceFallback: true,
    fallbackTolerance: 4,
    ghostClass: "is-ghost",
    onEnd: (event) => {
      if (event.oldIndex === undefined || event.newIndex === undefined || event.oldIndex === event.newIndex) {
        return;
      }
      const item = list.value?.children[event.newIndex];
      const before = list.value?.children[event.oldIndex + (event.oldIndex > event.newIndex ? 1 : 0)] ?? null;
      if (item) {
        list.value?.insertBefore(item, before);
      }
      move(event.oldIndex, event.newIndex);
    },
  });
});
onBeforeUnmount(() => sortable?.destroy());
</script>

<template>
  <div class="uda-rankin">
    <p class="uda-rankin__help">{{ t("rank_help") }}</p>
    <ol ref="list" class="uda-rankin__list">
      <li v-for="(id, i) in order" :key="id" class="uda-rankin__item">
        <span class="uda-rankin__handle" aria-hidden="true">⋮⋮</span>
        <span class="uda-rankin__pos">{{ i + 1 }}</span>
        <span class="uda-rankin__text">{{ text(id) }}</span>
        <span class="uda-rankin__btns">
          <button type="button" class="uda-icon-btn" :disabled="i === 0" :aria-label="t('rank_up', text(id))" @click="move(i, i - 1)">↑</button>
          <button type="button" class="uda-icon-btn" :disabled="i === order.length - 1" :aria-label="t('rank_down', text(id))" @click="move(i, i + 1)">↓</button>
        </span>
      </li>
    </ol>
    <p class="uda-sr-only" aria-live="polite">{{ live }}</p>
  </div>
</template>
