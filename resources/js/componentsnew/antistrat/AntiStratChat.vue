<template>
    <div class="asc">

        <!-- Header -->
        <div class="asc-head">
            <div class="asc-head__icon">
                <Icon icon="lucide:sparkles" />
            </div>
            <div class="asc-head__title">
                <p class="asc-head__name">AI Scout Chat</p>
                <p class="asc-head__sub">Ask anything about {{ opponentName }}</p>
            </div>
            <button v-if="messages.length > 0" class="asc-head__clear" @click="clearChat">
                Clear
            </button>
        </div>

        <!-- Messages -->
        <div ref="scrollRef" class="asc-messages">

            <!-- Empty state -->
            <div v-if="messages.length === 0" class="asc-empty">
                <div class="asc-empty__icon">
                    <Icon icon="lucide:message-circle" />
                </div>
                <div class="asc-empty__text">
                    <p class="asc-empty__headline">Ask me anything about this opponent</p>
                    <p class="asc-empty__body">I have access to all their stats, positions, and patterns</p>
                </div>
                <div class="asc-empty__chips">
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        class="asc-suggestion"
                        @click="sendSuggestion(suggestion)"
                    >
                        {{ suggestion }}
                    </button>
                </div>
            </div>

            <!-- Message list -->
            <template v-else>
                <div
                    v-for="(msg, i) in messages"
                    :key="i"
                    class="asc-msg"
                    :class="{ 'asc-msg--user': msg.role === 'user' }"
                >
                    <div class="asc-msg__avatar" :class="`asc-msg__avatar--${msg.role}`">
                        <Icon v-if="msg.role === 'assistant'" icon="lucide:sparkles" />
                        <span v-else>U</span>
                    </div>
                    <div
                        class="asc-msg__bubble"
                        :class="[`asc-msg__bubble--${msg.role}`, { 'asc-msg__bubble--error': msg.isError }]"
                    >
                        <div v-if="msg.role === 'assistant'" class="prose-chat" v-html="renderMarkdown(msg.content)" />
                        <span v-else>{{ msg.content }}</span>
                        <button
                            v-if="msg.isError && i === messages.length - 1"
                            class="asc-msg__retry"
                            :disabled="isLoading"
                            @click="retryLast"
                        >
                            <Icon icon="lucide:rotate-cw" class="asc-msg__retry-icon" />
                            Retry
                        </button>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div v-if="isLoading" class="asc-msg">
                    <div class="asc-msg__avatar asc-msg__avatar--assistant">
                        <Icon icon="lucide:sparkles" />
                    </div>
                    <div class="asc-msg__bubble asc-msg__bubble--assistant asc-typing">
                        <span class="asc-typing__dot" style="animation-delay: 0ms" />
                        <span class="asc-typing__dot" style="animation-delay: 150ms" />
                        <span class="asc-typing__dot" style="animation-delay: 300ms" />
                    </div>
                </div>
            </template>
        </div>

        <!-- Input -->
        <div class="asc-input">
            <div class="asc-input__row">
                <textarea
                    ref="inputRef"
                    v-model="input"
                    rows="1"
                    placeholder="Ask about positions, patterns, counter strats..."
                    class="asc-input__field"
                    @keydown.enter.exact.prevent="send"
                    @keydown.enter.shift.exact="() => {}"
                    @input="autoResize"
                />
                <button
                    class="asc-input__send"
                    :class="{ 'asc-input__send--active': input.trim() && !isLoading }"
                    :disabled="!input.trim() || isLoading"
                    @click="send"
                >
                    <Icon icon="lucide:send" />
                </button>
            </div>
            <p class="asc-input__hint">Enter to send, Shift+Enter for new line</p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, nextTick, watch } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import { Icon } from '@iconify/vue';
import { AppErrorCode } from '@/constants/errorCodes';
import { usePlan } from '@/composables/usePlan';
import { useUpgradeModal } from '@/composables/useUpgradeModal';

const props = defineProps<{
    opponentId:   number;
    opponentName: string;
}>();

const confirm = useConfirm();
const { can } = usePlan();
const { open: openUpgrade } = useUpgradeModal();

interface Message {
    role:    'user' | 'assistant';
    content: string;
    isError?: boolean;
}

const messages  = ref<Message[]>([]);
const input     = ref('');
const isLoading = ref(false);
const scrollRef = ref<HTMLElement | null>(null);
const inputRef  = ref<HTMLTextAreaElement | null>(null);

const suggestions = [
    'What are their biggest weaknesses?',
    'How do they play CT on this map?',
    'Who is their most dangerous player?',
    'What is their eco aggression like?',
    'How should we approach B-site?',
    'What counter strats do we have?',
];

const getCsrf = () =>
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

const scrollToBottom = () => {
    nextTick(() => {
        if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight;
    });
};

const autoResize = () => {
    const ta = inputRef.value;
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
};

const send = async () => {
    const text = input.value.trim();
    if (!text || isLoading.value) return;

    // Pro-only feature. Pre-empt the 403 so the user never types into a void.
    if (!can('ai_antistrat')) {
        openUpgrade({ feature: 'ai_antistrat', requiredTier: 'Pro' });
        return;
    }

    // Add user message
    messages.value.push({ role: 'user', content: text });
    input.value = '';
    nextTick(autoResize);
    scrollToBottom();

    isLoading.value = true;

    try {
        // Send last 10 messages as history (keep context window manageable)
        const history = messages.value.slice(-11, -1); // exclude the message we just added

        const res = await fetch(`/api/opponent-teams/${props.opponentId}/chat`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrf() },
            body:    JSON.stringify({ message: text, history }),
        });

        if (!res.ok) {
            // Backend tier gate → upgrade modal (this is raw fetch, so the
            // axios interceptor doesn't see it; handle it here).
            let body: any = null;
            try { body = await res.json(); } catch { /* non-JSON */ }
            if (body?.app_code === AppErrorCode.PLAN_LIMIT_EXCEEDED) {
                // Drop the user bubble we just added so the conversation stays clean.
                messages.value.pop();
                openUpgrade({
                    feature: body.feature ?? 'ai_antistrat',
                    requiredTier: body.required_tier ?? 'Pro',
                });
                return;
            }
            console.error('[AntiStratChat] chat request failed', res.status, res.statusText);
            throw new Error(`Chat request failed (${res.status})`);
        }

        const data = await res.json();
        messages.value.push({ role: 'assistant', content: data.reply ?? 'Sorry, no response.' });
    } catch (e) {
        console.error('[AntiStratChat] chat request error', e);
        messages.value.push({
            role:    'assistant',
            content: 'Sorry, something went wrong. Please try again.',
            isError: true,
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

const sendSuggestion = (suggestion: string) => {
    input.value = suggestion;
    send();
};

/**
 * Retry the last exchange: drop the failed assistant bubble, find the most
 * recent user message, and re-send it.
 */
const retryLast = () => {
    if (isLoading.value) return;
    // Remove the trailing error bubble(s)
    while (messages.value.length && messages.value[messages.value.length - 1].isError) {
        messages.value.pop();
    }
    // Pull the last user message back out and re-send it
    const lastUser = [...messages.value].reverse().find(m => m.role === 'user');
    if (!lastUser) return;
    if (messages.value[messages.value.length - 1]?.role === 'user') {
        messages.value.pop();
    }
    input.value = lastUser.content;
    send();
};

const clearChat = () => {
    confirm.require({
        message:     'Clear this conversation? All messages will be removed.',
        header:      'Clear conversation',
        icon:        'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        rejectProps: { label: 'Cancel', severity: 'secondary', outlined: true },
        acceptProps: { label: 'Clear', severity: 'danger' },
        accept: () => {
            messages.value = [];
            input.value    = '';
        },
    });
};

// Auto-scroll when messages change
watch(messages, scrollToBottom, { deep: true });

/**
 * Lightweight markdown renderer for AI chat responses.
 * Handles bold, italic, bullet lists, numbered lists, line breaks.
 * Does NOT use a full markdown library — keeps bundle small.
 *
 * SECURITY — XSS SAFETY (do not reorder):
 * Step 1 below escapes `&`, `<`, `>` and MUST stay the first transformation.
 * Every later pass only matches markdown punctuation (`*`, `_`, backtick, `-`,
 * digits) and newline boundaries — none of which can reconstruct an escaped
 * `&lt;`/`&gt;`/`&amp;` sequence into a live tag. Captured groups (`$1`) only
 * ever contain already-escaped user text; the only raw `<`/`>` introduced are
 * in the literal tag strings we author here. Result: no pass can re-open an
 * escaped sequence, so the output handed to v-html is provably tag-safe.
 * If you add a pass, keep it after the escape step and never emit user-
 * controlled `<`/`>`.
 */
const renderMarkdown = (text: string): string => {
    if (!text) return '';

    let html = text
        // Step 1 — escape HTML first to prevent XSS (MUST stay first; see above)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')

        // Step 2 — inline markup. Operates on already-escaped text; the only
        // raw angle brackets emitted are in our own literal tag strings.
        // Bold: **text** or __text__
        .replace(/\*\*(.+?)\*\*/g, '<strong class="md-strong">$1</strong>')
        .replace(/__(.+?)__/g, '<strong class="md-strong">$1</strong>')

        // Italic: *text* or _text_
        .replace(/\*([^*\n]+?)\*/g, '<em class="md-em">$1</em>')
        .replace(/_([^_\n]+?)_/g, '<em class="md-em">$1</em>')

        // Inline code: `code`
        .replace(/`([^`]+?)`/g, '<code class="md-code">$1</code>');

    // Step 3 — block layout: wrap lines in list / paragraph elements. `line`
    // here is already HTML-escaped (step 1) plus our own inline tags (step 2),
    // so interpolating it into <p>/<li> below cannot introduce new live tags.
    const lines  = html.split('\n');
    const result: string[] = [];
    let inList   = false;
    let listType = '';

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Unordered list: - item or • item
        const ulMatch = line.match(/^[\s]*[-•–]\s+(.+)/);
        // Ordered list: 1. item
        const olMatch = line.match(/^[\s]*\d+[.)]\s+(.+)/);

        if (ulMatch) {
            if (!inList || listType !== 'ul') {
                if (inList) result.push(listType === 'ul' ? '</ul>' : '</ol>');
                result.push('<ul class="md-ul">');
                inList = true; listType = 'ul';
            }
            result.push(`<li class="md-li"><span class="md-li__bullet">·</span><span>${ulMatch[1]}</span></li>`);
        } else if (olMatch) {
            if (!inList || listType !== 'ol') {
                if (inList) result.push(listType === 'ul' ? '</ul>' : '</ol>');
                result.push('<ol class="md-ol">');
                inList = true; listType = 'ol';
            }
            result.push(`<li class="md-li-ol">${olMatch[1]}</li>`);
        } else {
            if (inList) {
                result.push(listType === 'ul' ? '</ul>' : '</ol>');
                inList = false; listType = '';
            }
            if (line.trim() === '') {
                result.push('<div class="md-gap"></div>');
            } else {
                result.push(`<p class="md-p">${line}</p>`);
            }
        }
    }

    if (inList) result.push(listType === 'ul' ? '</ul>' : '</ol>');

    return result.join('');
};
</script>

<style scoped>
.asc {
    display: flex;
    flex-direction: column;
    height: 100%;
    border-radius: 6px;
    background: var(--th-surface, oklch(0.175 0.014 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    overflow: hidden;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
}

/* ── Header ── */
.asc-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    background: var(--th-surface-2, oklch(0.215 0.016 250));
    flex-shrink: 0;
}

.asc-head__icon {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 18%, transparent);
    color: var(--th-accent, #ff8c00);
    font-size: 14px;
}

.asc-head__title { flex: 1; min-width: 0; }

.asc-head__name {
    font-size: 14px;
    font-weight: 600;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    margin: 0;
}

.asc-head__sub {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 1px 0 0;
}

.asc-head__clear {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    background: none;
    border: none;
    cursor: pointer;
    transition: color 150ms ease;
}

.asc-head__clear:hover { color: var(--th-text-2, oklch(1 0 0 / 0.70)); }

/* ── Messages ── */
.asc-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    scrollbar-width: thin;
    scrollbar-color: var(--th-border-2, oklch(1 0 0 / 0.14)) transparent;
}

.asc-messages::-webkit-scrollbar { width: 4px; }
.asc-messages::-webkit-scrollbar-thumb {
    background: var(--th-border-2, oklch(1 0 0 / 0.14));
    border-radius: 3px;
}

/* ── Empty state ── */
.asc-empty {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 32px 0;
}

.asc-empty__icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 12%, transparent);
    color: var(--th-accent, #ff8c00);
    font-size: 20px;
}

.asc-empty__text { text-align: center; }

.asc-empty__headline {
    font-size: 14px;
    font-weight: 500;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    margin: 0;
}

.asc-empty__body {
    font-size: 12px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 4px 0 0;
}

.asc-empty__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    max-width: 320px;
}

.asc-suggestion {
    font-size: 11px;
    padding: 6px 10px;
    border-radius: 5px;
    text-align: left;
    background: var(--th-surface-2, oklch(0.215 0.016 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    color: var(--th-text-3, oklch(1 0 0 / 0.62));
    cursor: pointer;
    transition: border-color 150ms ease, color 150ms ease;
}

.asc-suggestion:hover {
    border-color: var(--th-border-3, oklch(1 0 0 / 0.22));
    color: var(--th-text, oklch(1 0 0 / 0.96));
}

/* ── Messages ── */
.asc-msg {
    display: flex;
    gap: 10px;
}

.asc-msg--user { flex-direction: row-reverse; }

.asc-msg__avatar {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    font-weight: 700;
}

.asc-msg__avatar--user {
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}

.asc-msg__avatar--assistant {
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 18%, transparent);
    color: var(--th-accent, #ff8c00);
}

.asc-msg__bubble {
    max-width: 85%;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
}

.asc-msg__bubble--user {
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 15%, transparent);
    color: var(--th-text, oklch(1 0 0 / 0.96));
    border-top-right-radius: 3px;
}

.asc-msg__bubble--assistant {
    background: var(--th-surface-2, oklch(0.215 0.016 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    border-top-left-radius: 3px;
}

.asc-msg__bubble--error {
    background: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 9%, var(--th-surface-2, oklch(0.215 0.016 250)));
    border-color: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 32%, transparent);
    color: var(--th-loss, oklch(0.62 0.20 22));
}

.asc-msg__retry {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 10px;
    font-weight: 600;
    color: var(--th-loss, oklch(0.62 0.20 22));
    background: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 12%, transparent);
    border: 1px solid color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 32%, transparent);
    cursor: pointer;
    transition: background 150ms ease;
}

.asc-msg__retry:hover:not(:disabled) {
    background: color-mix(in oklch, var(--th-loss, oklch(0.62 0.20 22)) 20%, transparent);
}

.asc-msg__retry:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.asc-msg__retry:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

.asc-msg__retry-icon { font-size: 11px; }

/* ── Typing indicator ── */
.asc-typing {
    display: flex;
    gap: 4px;
    align-items: center;
    padding: 12px 14px;
}

.asc-typing__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--th-text-3, oklch(1 0 0 / 0.62));
    animation: asc-pulse 1.2s ease-in-out infinite;
}

@keyframes asc-pulse {
    0%, 100% { opacity: 0.35; transform: translateY(1px); }
    50%      { opacity: 1;    transform: translateY(-1px); }
}

@media (prefers-reduced-motion: reduce) {
    .asc-typing__dot { animation: none; }
}

/* ── Input ── */
.asc-input {
    padding: 12px 16px;
    border-top: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    background: var(--th-surface-2, oklch(0.215 0.016 250));
    flex-shrink: 0;
}

.asc-input__row {
    display: flex;
    gap: 8px;
    align-items: flex-end;
}

.asc-input__field {
    flex: 1;
    background: var(--th-bg, oklch(0.125 0.012 250));
    border: 1px solid var(--th-border, oklch(1 0 0 / 0.09));
    border-radius: 6px;
    padding: 9px 12px;
    font-family: var(--th-sans, 'Schibsted Grotesk', sans-serif);
    font-size: 13px;
    color: var(--th-text, oklch(1 0 0 / 0.96));
    resize: none;
    max-height: 120px;
    overflow-y: auto;
    transition: border-color 150ms ease;
}

.asc-input__field::placeholder { color: var(--th-text-4, oklch(1 0 0 / 0.50)); }

.asc-input__field:focus {
    outline: none;
    border-color: var(--th-accent, #ff8c00);
}

.asc-input__send {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    border: none;
    background: var(--th-surface-3, oklch(0.26 0.018 250));
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    cursor: not-allowed;
    font-size: 14px;
    transition: background 150ms ease, color 150ms ease;
}

.asc-input__send--active {
    background: var(--th-accent, #ff8c00);
    color: oklch(0.15 0.005 53);
    cursor: pointer;
}

.asc-input__send--active:hover {
    background: var(--th-accent-2, oklch(0.62 0.20 50));
}

.asc-input__send:focus-visible {
    outline: 2px solid var(--th-accent, #ff8c00);
    outline-offset: 2px;
}

.asc-input__hint {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 9px;
    color: var(--th-text-4, oklch(1 0 0 / 0.50));
    margin: 6px 0 0;
}

/* ── Markdown chat content ── */
.prose-chat {
    font-size: 0.8125rem;
    line-height: 1.6;
}

.prose-chat :deep(.md-p) {
    margin: 0;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
    line-height: 1.6;
}

.prose-chat :deep(.md-gap) { height: 8px; }

.prose-chat :deep(.md-strong) {
    font-weight: 600;
    color: var(--th-text, oklch(1 0 0 / 0.96));
}

.prose-chat :deep(.md-em) {
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}

.prose-chat :deep(.md-code) {
    font-family: var(--th-mono, 'JetBrains Mono', monospace);
    font-size: 11px;
    color: var(--th-accent, #ff8c00);
    background: color-mix(in oklch, var(--th-accent, #ff8c00) 12%, transparent);
    padding: 1px 4px;
    border-radius: 3px;
}

.prose-chat :deep(.md-ul) {
    margin: 8px 0;
    padding-left: 12px;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.prose-chat :deep(.md-ol) {
    margin: 8px 0;
    padding-left: 12px;
    list-style: decimal;
    list-style-position: inside;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.prose-chat :deep(.md-li) {
    display: flex;
    gap: 8px;
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}

.prose-chat :deep(.md-li__bullet) {
    color: var(--th-accent, #ff8c00);
    flex-shrink: 0;
    margin-top: 2px;
}

.prose-chat :deep(.md-li-ol) {
    color: var(--th-text-2, oklch(1 0 0 / 0.70));
}
</style>
