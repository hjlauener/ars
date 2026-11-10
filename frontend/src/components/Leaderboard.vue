<script setup lang="ts">
import type { LeaderEntry } from "../types.ts";
import { useI18n } from "../util.ts";

/** quiz ranking: points of the session so far (top 10) */
defineProps<{ entries: LeaderEntry[] }>();
const { t } = useI18n();
</script>

<template>
  <section class="uda-leader">
    <h3 class="uda-leader__title">{{ t("leaderboard") }}</h3>
    <p v-if="entries.length === 0" class="uda-note">{{ t("leaderboard_empty") }}</p>
    <ol v-else class="uda-leader__list">
      <li v-for="(e, i) in entries" :key="i" class="uda-leader__row" :class="{ 'is-me': e.me }">
        <span class="uda-leader__pos">{{ i + 1 }}</span>
        <span class="uda-leader__name">{{ e.name || t("anonymous") }}<template v-if="e.me"> ({{ t("you") }})</template></span>
        <span class="uda-leader__points">{{ t("points_n", e.points) }}</span>
      </li>
    </ol>
  </section>
</template>
