<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useI18n } from "../util.ts";

/**
 * Word cloud: frequent words larger. Own simple layout (no library): words
 * are placed from the centre outwards on a spiral, without overlapping.
 * Real text (selectable, readable for screen readers via the list).
 */
const props = defineProps<{ words: { text: string; count: number }[]; large?: boolean }>();
const { t } = useI18n();

interface Placed {
  text: string;
  count: number;
  x: number;
  y: number;
  size: number;
  tone: number;
}

const box = ref<HTMLElement | null>(null);
const placed = ref<Placed[]>([]);
const width = ref(0);
const height = ref(0);
let observer: ResizeObserver | null = null;
let canvas: HTMLCanvasElement | null = null;

const sorted = computed(() => [...props.words].sort((a, b) => b.count - a.count));

function measure(text: string, size: number, font: string): { w: number; h: number } {
  canvas ??= document.createElement("canvas");
  const ctx = canvas.getContext("2d");
  if (!ctx) {
    return { w: text.length * size * 0.6, h: size };
  }
  ctx.font = `600 ${size}px ${font}`;
  return { w: ctx.measureText(text).width, h: size * 1.05 };
}

function layout(): void {
  const el = box.value;
  if (!el) {
    return;
  }
  const W = el.clientWidth;
  const H = el.clientHeight;
  width.value = W;
  height.value = H;
  const words = sorted.value;
  if (W === 0 || H === 0 || words.length === 0) {
    placed.value = [];
    return;
  }
  const font = getComputedStyle(el).fontFamily || "sans-serif";
  const min = words[words.length - 1].count;
  const max = words[0].count;
  const maxSize = Math.min(props.large ? 84 : 56, H / 3.2);
  const minSize = Math.max(13, maxSize / 4.5);

  for (let scale = 1; scale > 0.3; scale *= 0.85) {
    const result: Placed[] = [];
    const rects: { x: number; y: number; w: number; h: number }[] = [];
    let ok = true;
    for (const [i, word] of words.entries()) {
      const f = max === min ? 1 : Math.sqrt((word.count - min) / (max - min));
      const size = Math.round((minSize + (maxSize - minSize) * f) * scale);
      const { w, h } = measure(word.text, size, font);
      const pad = size * 0.18;
      let spot: { x: number; y: number } | null = null;
      for (let step = 0; step < 1800; step++) {
        const angle = step * 0.32;
        const radius = 2.2 * angle;
        const x = W / 2 + radius * Math.cos(angle) * (W / H > 1 ? Math.min(2, W / H) : 1) - w / 2;
        const y = H / 2 + radius * Math.sin(angle) - h / 2;
        if (x < 0 || y < 0 || x + w > W || y + h > H) {
          continue;
        }
        const hit = rects.some((r) => x < r.x + r.w + pad && x + w + pad > r.x && y < r.y + r.h + pad && y + h + pad > r.y);
        if (!hit) {
          spot = { x, y };
          break;
        }
      }
      if (!spot) {
        ok = false;
        break;
      }
      rects.push({ x: spot.x, y: spot.y, w, h });
      result.push({ text: word.text, count: word.count, x: spot.x + w / 2, y: spot.y + h / 2, size, tone: i % 3 });
    }
    if (ok) {
      placed.value = result;
      return;
    }
  }
  placed.value = [];
}

onMounted(() => {
  observer = new ResizeObserver(() => layout());
  if (box.value) {
    observer.observe(box.value);
  }
  layout();
});
onBeforeUnmount(() => observer?.disconnect());
watch(() => props.words, layout, { deep: true });
</script>

<template>
  <div class="uda-cloud" :class="{ 'uda-cloud--large': large }">
    <div ref="box" class="uda-cloud__box" aria-hidden="true">
      <span
        v-for="w in placed"
        :key="w.text"
        class="uda-cloud__word"
        :class="[`tone-${w.tone}`, { 'is-big': w.size >= 30 }]"
        :style="{ left: w.x + 'px', top: w.y + 'px', fontSize: w.size + 'px' }"
        :title="`${w.text} (${w.count})`"
        >{{ w.text }}</span
      >
    </div>
    <ul class="uda-sr-only">
      <li v-for="w in sorted" :key="w.text">{{ w.text }}: {{ w.count }}</li>
    </ul>
    <p v-if="words.length === 0" class="uda-cloud__empty">{{ t("results_no_words") }}</p>
  </div>
</template>
