<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import type { JoinConfig, ParticipantState } from "../types.ts";
import { Poller, deviceToken, formatCode, postJson, useI18n } from "../util.ts";
import ResultsDisplay from "../components/ResultsDisplay.vue";
import RankInput from "./RankInput.vue";

/**
 * Page of the participants (smartphone): enter the code, wait for the
 * start, answer the current question, see results when the presenter
 * shows them.
 */
const props = defineProps<{ config: JoinConfig }>();
const { t, tn } = useI18n();

const code = ref(props.config.code);
const codeInput = ref("");
const codeError = ref("");
const state = ref<ParticipantState | null>(null);
const offline = ref(false);
const sending = ref(false);
const errorText = ref("");
const editing = ref(false);
const token = props.config.loggedIn ? "" : deviceToken();

// answer being prepared
const chosen = ref<string[]>([]);
const words = ref<string[]>([]);
const order = ref<string[]>([]);

// name for the quiz ranking
const nameInput = ref("");
const nameSkipped = ref(sessionFlag("uda-name-skipped"));

const remaining = ref<number | null>(null);
let deadline = 0;
let countdown: number | undefined;

const question = computed(() => state.value?.question ?? null);
const answered = computed(() => !!state.value?.myAnswer);
const voting = computed(() => !!state.value?.voting);
const canChange = computed(() => voting.value && question.value?.type !== "quiz");
const showForm = computed(() => voting.value && (!answered.value || editing.value));
const askName = computed(() => state.value?.status === "running" && state.value.hasQuiz && !state.value.name && !nameSkipped.value);
const maxWords = computed(() => question.value?.settings.maxWords ?? 3);
const limit = computed(() => question.value?.settings.timeLimit ?? 0);

function sessionFlag(key: string): boolean {
  try {
    return window.sessionStorage.getItem(key) === "1";
  } catch {
    return false;
  }
}

function apply(next: ParticipantState): void {
  const before = state.value?.question?.id;
  state.value = next;
  errorText.value = next.error ?? "";
  if (next.status === "unknown") {
    codeError.value = t("join_unknown_code");
    code.value = "";
    poller.stop();
    return;
  }
  if (next.question && next.question.id !== before) {
    resetDraft();
  }
  const ms = next.remainingMs;
  if (typeof ms === "number") {
    deadline = performance.now() + ms;
    remaining.value = ms;
  } else {
    deadline = 0;
    remaining.value = null;
  }
}

function resetDraft(): void {
  const q = state.value?.question;
  editing.value = false;
  chosen.value = [];
  words.value = Array.from({ length: q?.settings.maxWords ?? 3 }, () => "");
  order.value = q?.answers.map((a) => a.id) ?? [];
}

async function request(body: Record<string, unknown>): Promise<void> {
  apply(await postJson<ParticipantState>(props.config.apiUrl, { code: code.value, token, ...body }));
}

const poller = new Poller(() => request({ action: "state" }), 1500, (failing) => (offline.value = failing));

async function send(answer: string[]): Promise<void> {
  if (!question.value || sending.value) {
    return;
  }
  sending.value = true;
  try {
    await request({ action: "answer", questionId: question.value.id, answer });
    editing.value = false;
    offline.value = false;
  } catch {
    offline.value = true;
  } finally {
    sending.value = false;
  }
}

function submitChoice(): void {
  if (chosen.value.length > 0) {
    void send(chosen.value);
  }
}

function toggle(id: string): void {
  if (question.value?.settings.multiple) {
    chosen.value = chosen.value.includes(id) ? chosen.value.filter((x) => x !== id) : [...chosen.value, id];
  } else {
    chosen.value = [id];
  }
}

function submitWords(): void {
  const list = words.value.map((w) => w.trim()).filter(Boolean);
  if (list.length > 0) {
    void send(list);
  }
}

function startEdit(): void {
  const mine = state.value?.myAnswer ?? [];
  if (question.value?.type === "rank") {
    order.value = [...mine];
  } else if (question.value?.type === "cloud") {
    words.value = Array.from({ length: maxWords.value }, (_, i) => mine[i] ?? "");
  } else {
    chosen.value = [...mine];
  }
  editing.value = true;
}

async function saveName(skip: boolean): Promise<void> {
  if (skip || nameInput.value.trim() === "") {
    nameSkipped.value = true;
    try {
      window.sessionStorage.setItem("uda-name-skipped", "1");
    } catch {
      // ignore
    }
    return;
  }
  await request({ action: "name", name: nameInput.value.trim() });
}

function submitCode(): void {
  const digits = codeInput.value.replace(/\D/g, "");
  if (digits.length !== 6) {
    codeError.value = t("join_code_invalid");
    return;
  }
  const url = new URL(window.location.href);
  url.searchParams.set("c", digits);
  window.location.href = url.toString();
}

function changeCode(): void {
  const url = new URL(window.location.href);
  url.searchParams.delete("c");
  window.location.href = url.toString();
}

const answerText = (id: string): string => question.value?.answers.find((a) => a.id === id)?.text ?? id;
const myAnswerText = computed(() => {
  const mine = state.value?.myAnswer ?? [];
  if (question.value?.type === "cloud") {
    return mine.join(", ");
  }
  if (question.value?.type === "rank") {
    return mine.map((id, i) => `${i + 1}. ${answerText(id)}`).join("  ");
  }
  return mine.map(answerText).join(", ");
});
const quizCorrect = computed(() => {
  const quiz = state.value?.quiz;
  const mine = state.value?.myAnswer ?? [];
  return !!quiz && mine.length > 0 && quiz.correct.includes(mine[0]);
});

watch(
  () => state.value?.status,
  (status) => {
    if (status === "login" || status === "denied") {
      poller.stop();
    }
  },
);

onMounted(() => {
  if (code.value) {
    poller.start();
  }
  countdown = window.setInterval(() => {
    if (deadline > 0) {
      remaining.value = Math.max(0, deadline - performance.now());
    }
  }, 200);
});
onBeforeUnmount(() => {
  poller.stop();
  window.clearInterval(countdown);
});
</script>

<template>
  <div class="uda-join">
    <header class="uda-join__head">
      <span class="uda-join__brand">Udon ARS</span>
      <span v-if="state?.title" class="uda-join__title">{{ state.title }}</span>
    </header>

    <main class="uda-join__main">
      <p v-if="offline" class="uda-alert" role="alert">{{ t("err_connection") }}</p>

      <!-- enter the code -->
      <form v-if="!code" class="uda-card uda-codeform" @submit.prevent="submitCode">
        <h1 class="uda-join__h1">{{ t("join_enter_code") }}</h1>
        <label class="uda-sr-only" for="uda-code">{{ t("code") }}</label>
        <input
          id="uda-code"
          v-model="codeInput"
          class="uda-input uda-codeform__input"
          inputmode="numeric"
          autocomplete="off"
          maxlength="7"
          placeholder="123 456"
          :aria-invalid="codeError !== ''"
          :aria-describedby="codeError ? 'uda-code-error' : undefined"
        />
        <p v-if="codeError" id="uda-code-error" class="uda-error">{{ codeError }}</p>
        <button type="submit" class="uda-btn uda-btn--primary uda-btn--block uda-btn--large">{{ t("join_go") }}</button>
      </form>

      <div v-else-if="!state" class="uda-card uda-wait"><span class="uda-pulse"></span>{{ t("join_loading") }}</div>

      <div v-else-if="state.status === 'login'" class="uda-card">
        <h1 class="uda-join__h1">{{ t("join_login_title") }}</h1>
        <p>{{ t("join_login_text") }}</p>
        <a class="uda-btn uda-btn--primary uda-btn--block uda-btn--large" :href="state.loginUrl">{{ t("join_login") }}</a>
      </div>

      <div v-else-if="state.status === 'denied'" class="uda-card">
        <h1 class="uda-join__h1">{{ t("join_denied_title") }}</h1>
        <p>{{ t("join_denied_text") }}</p>
      </div>

      <div v-else-if="state.status === 'waiting'" class="uda-card uda-wait">
        <span class="uda-pulse" aria-hidden="true"></span>
        <div>
          <h1 class="uda-join__h1">{{ t("join_waiting_title") }}</h1>
          <p>{{ t("join_waiting_text") }}</p>
        </div>
      </div>

      <div v-else-if="state.status === 'ended'" class="uda-card uda-wait">
        <span class="uda-done-mark" aria-hidden="true">✓</span>
        <div>
          <h1 class="uda-join__h1">{{ t("join_ended_title") }}</h1>
          <p>{{ t("join_ended_text") }}</p>
        </div>
      </div>

      <!-- name for the quiz ranking -->
      <form v-else-if="askName" class="uda-card" @submit.prevent="saveName(false)">
        <h1 class="uda-join__h1">{{ t("join_name_title") }}</h1>
        <p>{{ t("join_name_text") }}</p>
        <label class="uda-sr-only" for="uda-name">{{ t("join_name_label") }}</label>
        <input id="uda-name" v-model="nameInput" class="uda-input" maxlength="30" autocomplete="nickname" :placeholder="t('join_name_label')" />
        <div class="uda-row">
          <button type="button" class="uda-btn" @click="saveName(true)">{{ t("join_name_skip") }}</button>
          <button type="submit" class="uda-btn uda-btn--primary">{{ t("join_name_save") }}</button>
        </div>
      </form>

      <!-- the current question -->
      <section v-else-if="question" class="uda-card uda-question" :aria-labelledby="'uda-q-' + question.id">
        <p class="uda-question__pos">{{ t("present_position", state.position?.index ?? 1, state.position?.count ?? 1) }}</p>
        <h1 :id="'uda-q-' + question.id" class="uda-question__title">{{ question.title }}</h1>

        <div v-if="question.type === 'quiz' && voting && remaining !== null" class="uda-timebar" role="timer" :aria-label="tn('seconds', Math.ceil(remaining / 1000))">
          <div class="uda-timebar__fill" :style="{ width: (limit ? (remaining / (limit * 1000)) * 100 : 0) + '%' }"></div>
          <span class="uda-timebar__num">{{ Math.ceil(remaining / 1000) }}</span>
        </div>

        <p v-if="errorText" class="uda-error" role="alert">{{ errorText }}</p>

        <!-- answer forms -->
        <template v-if="showForm">
          <div v-if="question.type === 'quiz'" class="uda-quizbtns">
            <button
              v-for="(a, i) in question.answers"
              :key="a.id"
              type="button"
              class="uda-quizbtn"
              :disabled="sending"
              @click="send([a.id])"
            >
              <span class="uda-quizbtn__letter">{{ String.fromCharCode(65 + i) }}</span>{{ a.text }}
            </button>
          </div>

          <form v-else-if="question.type === 'choice'" class="uda-options" @submit.prevent="submitChoice">
            <p class="uda-options__help">{{ question.settings.multiple ? t("join_choose_many") : t("join_choose_one") }}</p>
            <div :role="question.settings.multiple ? 'group' : 'radiogroup'" :aria-labelledby="'uda-q-' + question.id" class="uda-options__list">
              <label v-for="a in question.answers" :key="a.id" class="uda-option" :class="{ 'is-on': chosen.includes(a.id) }">
                <input
                  :type="question.settings.multiple ? 'checkbox' : 'radio'"
                  name="uda-choice"
                  :value="a.id"
                  :checked="chosen.includes(a.id)"
                  @change="toggle(a.id)"
                />
                <span>{{ a.text }}</span>
              </label>
            </div>
            <button type="submit" class="uda-btn uda-btn--primary uda-btn--block uda-btn--large" :disabled="sending || chosen.length === 0">{{ t("join_send") }}</button>
          </form>

          <form v-else-if="question.type === 'cloud'" class="uda-words" @submit.prevent="submitWords">
            <p class="uda-options__help">{{ tn("join_words_help", maxWords) }}</p>
            <input
              v-for="i in maxWords"
              :key="i"
              v-model="words[i - 1]"
              class="uda-input"
              maxlength="40"
              autocomplete="off"
              :aria-label="t('join_word_n', i)"
              :placeholder="t('join_word_n', i)"
            />
            <button type="submit" class="uda-btn uda-btn--primary uda-btn--block uda-btn--large" :disabled="sending || words.every((w) => !w.trim())">{{ t("join_send") }}</button>
          </form>

          <form v-else-if="question.type === 'rank'" @submit.prevent="send(order)">
            <RankInput v-model="order" :items="question.answers" />
            <button type="submit" class="uda-btn uda-btn--primary uda-btn--block uda-btn--large" :disabled="sending">{{ t("join_send") }}</button>
          </form>

          <button v-if="editing" type="button" class="uda-btn uda-btn--ghost uda-btn--block" @click="editing = false">{{ t("cancel") }}</button>
        </template>

        <!-- answered -->
        <div v-else-if="answered && !(question.type === 'quiz' && !voting)" class="uda-done">
          <p class="uda-done__title"><span aria-hidden="true">✓</span> {{ t(question.type === "quiz" ? "join_quiz_sent" : "join_saved") }}</p>
          <p class="uda-done__answer">{{ myAnswerText }}</p>
          <button v-if="canChange" type="button" class="uda-btn" @click="startEdit">{{ t("join_change") }}</button>
        </div>

        <!-- quiz resolved -->
        <div v-else-if="question.type === 'quiz' && !voting && state.quiz" class="uda-quizres" :class="answered ? (quizCorrect ? 'is-right' : 'is-wrong') : ''">
          <p class="uda-quizres__title">
            {{ !answered ? t("join_quiz_none") : quizCorrect ? t("join_quiz_right") : t("join_quiz_wrong") }}
          </p>
          <p v-if="answered && quizCorrect" class="uda-quizres__points">+{{ t("points_n", state.quiz.points) }}</p>
          <p class="uda-quizres__total">
            {{ t("join_quiz_total", state.quiz.total) }}
            <template v-if="state.quiz.rank"> · {{ t("join_quiz_rank", state.quiz.rank, state.quiz.of) }}</template>
          </p>
          <p v-if="!quizCorrect" class="uda-quizres__correct">{{ t("quiz_correct_answer") }}: {{ state.quiz.correct.map(answerText).join(", ") }}</p>
        </div>

        <p v-else-if="!voting" class="uda-note">{{ t("join_closed") }}</p>
      </section>

      <section v-if="state?.status === 'running' && state.showResults && state.results && !askName && (answered || !voting)" class="uda-card">
        <h2 class="uda-join__h2">{{ t("join_results") }}</h2>
        <ResultsDisplay :results="state.results" :reveal="!voting" :leaderboard="question?.type === 'quiz' && !voting" />
      </section>
    </main>

    <footer v-if="code" class="uda-join__foot">
      <span>{{ t("code") }} {{ formatCode(code) }}</span>
      <button type="button" class="uda-linkbtn" @click="changeCode">{{ t("join_other_code") }}</button>
    </footer>
  </div>
</template>
