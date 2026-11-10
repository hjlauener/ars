<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import type { AppConfig, PresenterState, Question } from "../types.ts";
import { Poller, formatCode, postJson, useI18n } from "../util.ts";
import ConfirmDialog from "./ConfirmDialog.vue";
import QrCode from "./QrCode.vue";
import ResultsDisplay from "./ResultsDisplay.vue";
import TypeIcon from "./TypeIcon.vue";

/**
 * Tab "Präsentieren": the stage for the projector (full screen possible)
 * with code / QR code, the current question, live results and the controls
 * (navigate, open/close voting, show results, end).
 */
const props = defineProps<{ config: AppConfig & { editorUrl?: string } }>();
const { t, tn } = useI18n();
const api = props.config.apiUrl ?? "";

const state = ref<PresenterState | null>(null);
const offline = ref(false);
const errorText = ref("");
const busy = ref(false);
const bigQr = ref(false);
const confirmEnd = ref(false);
const confirmReset = ref(false);
const stage = ref<HTMLElement | null>(null);
const isFullscreen = ref(false);
/** the stage fills the browser window; real full screen in addition where the browser allows it */
const fillWindow = ref(false);
const remaining = ref<number | null>(null);
let deadline = 0;
let countdown: number | undefined;

const session = computed(() => state.value?.session ?? null);
const questions = computed<Question[]>(() => state.value?.questions ?? []);
const index = computed(() => questions.value.findIndex((q) => q.id === session.value?.questionId));
const question = computed(() => (index.value >= 0 ? questions.value[index.value] : null));
const answers = computed(() => question.value?.answers.filter((a) => a.text.trim() !== "") ?? []);
const total = computed(() => state.value?.results?.total ?? 0);
const isQuiz = computed(() => question.value?.type === "quiz");
const timeLimit = computed(() => (isQuiz.value ? (question.value?.settings.timeLimit ?? 0) : 0));
const code = formatCode(props.config.code);

function apply(next: PresenterState): void {
  state.value = next;
  errorText.value = next.error ?? "";
  const ms = next.remainingMs;
  if (typeof ms === "number") {
    deadline = performance.now() + ms;
    remaining.value = ms;
  } else {
    deadline = 0;
    remaining.value = null;
  }
}

async function send(body: Record<string, unknown>): Promise<void> {
  busy.value = true;
  try {
    apply(await postJson<PresenterState>(api, { ...body, sessionId: session.value?.id ?? 0 }));
    offline.value = false;
  } catch {
    offline.value = true;
  } finally {
    busy.value = false;
  }
}

const poller = new Poller(
  async () => apply(await postJson<PresenterState>(api, { action: "present" })),
  1000,
  (failing) => (offline.value = failing),
);

function go(delta: number): void {
  const next = questions.value[index.value + delta];
  if (next && !busy.value) {
    void send({ action: "goto", questionId: next.id });
  }
}

function toggleFullscreen(): void {
  if (fillWindow.value || document.fullscreenElement) {
    fillWindow.value = false;
    if (document.fullscreenElement) {
      void document.exitFullscreen().catch(() => {});
    }
    return;
  }
  fillWindow.value = true;
  if (document.fullscreenEnabled && stage.value?.requestFullscreen) {
    stage.value.requestFullscreen().catch(() => {});
  }
}

function onFullscreen(): void {
  const was = isFullscreen.value;
  isFullscreen.value = document.fullscreenElement === stage.value;
  if (was && !isFullscreen.value) {
    // left real full screen (Escape): also leave the window-filling view
    fillWindow.value = false;
  }
}

function onKey(event: KeyboardEvent): void {
  const target = event.target as HTMLElement | null;
  if (event.key === "Escape" && fillWindow.value && !bigQr.value) {
    fillWindow.value = false;
    return;
  }
  if (!session.value || target?.closest("input, textarea, select, dialog") || event.altKey || event.ctrlKey || event.metaKey) {
    return;
  }
  if (event.key === "ArrowRight" || event.key === "PageDown") {
    event.preventDefault();
    go(1);
  } else if (event.key === "ArrowLeft" || event.key === "PageUp") {
    event.preventDefault();
    go(-1);
  }
}

onMounted(() => {
  poller.start();
  document.addEventListener("keydown", onKey);
  document.addEventListener("fullscreenchange", onFullscreen);
  countdown = window.setInterval(() => {
    if (deadline > 0) {
      remaining.value = Math.max(0, deadline - performance.now());
    }
  }, 200);
});
onBeforeUnmount(() => {
  poller.stop();
  document.removeEventListener("keydown", onKey);
  document.removeEventListener("fullscreenchange", onFullscreen);
  window.clearInterval(countdown);
});

const seconds = computed(() => (remaining.value === null ? null : Math.ceil(remaining.value / 1000)));
const ringFraction = computed(() =>
  remaining.value === null || timeLimit.value === 0 ? 0 : Math.max(0, Math.min(1, remaining.value / (timeLimit.value * 1000))),
);
const showResults = computed(() => !!session.value?.showResults && !!state.value?.results && !(isQuiz.value && session.value.voting));
</script>

<template>
  <div class="uda-present">
    <p v-if="offline" class="uda-alert" role="alert">{{ t("err_connection") }}</p>
    <p v-if="errorText" class="uda-alert" role="alert">{{ errorText }}</p>

    <!-- not running: start -->
    <section v-if="state && !session" class="uda-panel uda-start">
      <div class="uda-start__text">
        <h2 class="uda-start__title">{{ config.title }}</h2>
        <p class="uda-start__meta">{{ tn("editor_n_questions", questions.length) }}</p>
        <p v-if="questions.length === 0" class="uda-note">
          {{ t("present_no_questions") }}
          <a v-if="config.editorUrl" :href="config.editorUrl">{{ t("present_to_editor") }}</a>
        </p>
        <p v-else class="uda-start__hint">{{ t("present_start_hint") }}</p>
        <button type="button" class="uda-btn uda-btn--primary uda-btn--large" :disabled="busy || questions.length === 0" @click="send({ action: 'start' })">
          {{ t("present_start") }}
        </button>
      </div>
      <div class="uda-start__join">
        <QrCode :value="config.joinUrl" :label="t('qr_label')" />
        <p class="uda-start__code">
          <span>{{ t("code") }}</span><strong>{{ code }}</strong>
        </p>
        <p class="uda-start__url">{{ config.displayUrl }}</p>
      </div>
    </section>

    <!-- running -->
    <section v-if="session" ref="stage" class="uda-stage" :class="{ 'is-fullscreen': isFullscreen || fillWindow, 'is-fill': fillWindow }">
      <header class="uda-stage__bar">
        <button type="button" class="uda-stage__qr" :title="t('present_qr_big')" @click="bigQr = true">
          <QrCode :value="config.joinUrl" :label="t('qr_label')" />
        </button>
        <p class="uda-stage__join">
          <span class="uda-stage__url">{{ t("present_join_at", config.displayUrl) }}</span>
          <span class="uda-stage__code">{{ t("code") }} <strong>{{ code }}</strong></span>
        </p>
        <span class="uda-stage__people" :title="t('present_participants')">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="9" cy="8" r="3.2" /><path d="M3 19c.6-3.4 3-5 6-5s5.4 1.6 6 5" /><circle cx="17" cy="9" r="2.4" /><path d="M16 14.2c2.4.2 4.3 1.6 4.9 4.3" />
          </svg>
          <strong>{{ state?.participants?.active ?? 0 }}</strong>
          <span class="uda-sr-only">{{ t("present_participants") }}</span>
        </span>
      </header>

      <main v-if="question" class="uda-stage__main" aria-live="polite">
        <div class="uda-stage__meta">
          <span class="uda-chip"><TypeIcon :type="question.type" :size="18" /> {{ t(`type_${question.type}`) }}</span>
          <span>{{ t("present_position", index + 1, questions.length) }}</span>
          <span v-if="!session.voting" class="uda-badge">{{ t("present_voting_closed") }}</span>
        </div>
        <h1 class="uda-stage__question">{{ question.title || t("editor_untitled") }}</h1>

        <!-- quiz while answering: answers and countdown -->
        <div v-if="isQuiz && session.voting" class="uda-quizlive">
          <ol class="uda-quizlive__answers">
            <li v-for="(a, i) in answers" :key="a.id"><span class="uda-quizlive__letter">{{ String.fromCharCode(65 + i) }}</span>{{ a.text }}</li>
          </ol>
          <div class="uda-quizlive__side">
            <div v-if="seconds !== null" class="uda-ring" role="timer" :aria-label="tn('seconds', seconds)">
              <svg viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" class="uda-ring__track" />
                <circle cx="60" cy="60" r="52" class="uda-ring__arc" pathLength="100" :stroke-dasharray="`${ringFraction * 100} 100`" />
              </svg>
              <span class="uda-ring__num">{{ seconds }}</span>
            </div>
            <p class="uda-count"><strong>{{ total }}</strong> {{ tn("answers_word", total) }}</p>
          </div>
        </div>

        <ResultsDisplay
          v-else-if="showResults && state?.results"
          :results="state.results"
          :large="true"
          :reveal="!session.voting"
          :leaderboard="isQuiz && !session.voting"
        />

        <div v-else class="uda-hidden-results">
          <p class="uda-count uda-count--big"><strong>{{ total }}</strong> {{ tn("answers_word", total) }}</p>
          <p class="uda-note">{{ t("present_results_hidden") }}</p>
        </div>

        <p v-if="showResults && question.type !== 'cloud'" class="uda-stage__total">{{ tn("present_n_answered", total) }}</p>
      </main>

      <footer class="uda-stage__controls">
        <div class="uda-controls__nav">
          <button type="button" class="uda-btn" :disabled="busy || index <= 0" :aria-label="t('present_prev')" @click="go(-1)">← <span class="uda-hide-sm">{{ t("present_prev") }}</span></button>
          <span class="uda-controls__pos">{{ index + 1 }} / {{ questions.length }}</span>
          <button type="button" class="uda-btn uda-btn--primary" :disabled="busy || index >= questions.length - 1" :aria-label="t('present_next')" @click="go(1)"><span class="uda-hide-sm">{{ t("present_next") }}</span> →</button>
        </div>
        <div class="uda-controls__main">
          <button type="button" class="uda-btn" :disabled="busy" @click="send({ action: 'voting', open: !session.voting })">
            {{ session.voting ? t("present_close_voting") : t("present_open_voting") }}
          </button>
          <button type="button" class="uda-btn" :disabled="busy || (isQuiz && session.voting)" @click="send({ action: 'results', show: !session.showResults })">
            {{ session.showResults ? t("present_hide_results") : t("present_show_results") }}
          </button>
        </div>
        <div class="uda-controls__more">
          <button type="button" class="uda-btn uda-btn--ghost" :disabled="busy" @click="confirmReset = true">{{ t("present_reset") }}</button>
          <button type="button" class="uda-btn uda-btn--ghost" @click="toggleFullscreen">{{ isFullscreen || fillWindow ? t("present_exit_fullscreen") : t("present_fullscreen") }}</button>
          <button type="button" class="uda-btn uda-btn--ghost uda-btn--danger-text" :disabled="busy" @click="confirmEnd = true">{{ t("present_end") }}</button>
        </div>
      </footer>

      <div v-if="bigQr" class="uda-qr-overlay" role="dialog" :aria-label="t('present_qr_big')" @click="bigQr = false" @keydown.esc="bigQr = false">
        <div class="uda-qr-overlay__card" @click.stop>
          <QrCode :value="config.joinUrl" :label="t('qr_label')" />
          <p class="uda-qr-overlay__url">{{ config.displayUrl }}</p>
          <p class="uda-qr-overlay__code">{{ t("code") }} <strong>{{ code }}</strong></p>
          <button type="button" class="uda-btn" autofocus @click="bigQr = false">{{ t("close") }}</button>
        </div>
      </div>

      <ConfirmDialog
        :open="confirmEnd"
        :title="t('present_end_title')"
        :text="t('present_end_text')"
        :confirm="t('present_end')"
        @confirm="confirmEnd = false; send({ action: 'end' })"
        @cancel="confirmEnd = false"
      />
      <ConfirmDialog
        :open="confirmReset"
        :title="t('present_reset_title')"
        :text="t('present_reset_text')"
        :confirm="t('present_reset')"
        @confirm="confirmReset = false; send({ action: 'reset' })"
        @cancel="confirmReset = false"
      />
    </section>
  </div>
</template>
