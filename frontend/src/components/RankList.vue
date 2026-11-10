<script setup lang="ts">
import type { RankResult } from "../types.ts";
import { useI18n } from "../util.ts";

/**
 * Result of a ranking: the items sorted by their average place; the bar
 * shows the score (always first = full, always last = empty).
 */
defineProps<{ items: RankResult[]; large?: boolean }>();
const { t, lang } = useI18n();
const avg = (n: number | null): string =>
  n === null ? "–" : n.toLocaleString(lang === "de" ? "de-CH" : lang, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
</script>

<template>
  <ol class="uda-rank" :class="{ 'uda-rank--large': large }">
    <li v-for="(item, i) in items" :key="item.id" class="uda-rank__row">
      <span class="uda-rank__pos">{{ i + 1 }}</span>
      <div class="uda-rank__body">
        <div class="uda-rank__label">
          <span class="uda-rank__text">{{ item.text }}</span>
          <span class="uda-rank__avg">{{ t("results_avg_place", avg(item.avgRank)) }}</span>
        </div>
        <div class="uda-rank__track">
          <div class="uda-rank__fill" :style="{ width: Math.max(2, item.score * 100) + '%' }"></div>
        </div>
      </div>
    </li>
  </ol>
</template>
