<script setup lang="ts">
import type { Results } from "../types.ts";
import ChoiceBars from "./ChoiceBars.vue";
import Leaderboard from "./Leaderboard.vue";
import RankList from "./RankList.vue";
import WordCloud from "./WordCloud.vue";

/** results of one question, whatever its type */
defineProps<{ results: Results; large?: boolean; reveal?: boolean; leaderboard?: boolean }>();
</script>

<template>
  <div class="uda-results">
    <ChoiceBars
      v-if="results.type === 'choice' || results.type === 'quiz'"
      :options="results.options ?? []"
      :total="results.total"
      :reveal="results.type === 'quiz' && reveal"
      :large="large"
    />
    <WordCloud v-else-if="results.type === 'cloud'" :words="results.words ?? []" :large="large" />
    <RankList v-else-if="results.type === 'rank'" :items="results.items ?? []" :large="large" />
    <Leaderboard v-if="results.type === 'quiz' && leaderboard && results.leaderboard" :entries="results.leaderboard" />
  </div>
</template>
