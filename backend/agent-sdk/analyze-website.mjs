#!/usr/bin/env node
/**
 * Runs klicklocal-webanalyze via Claude Agent SDK.
 * Usage: node analyze-website.mjs <url>
 * Stdout: JSON result (single line)
 */
import { query } from '@anthropic-ai/claude-agent-sdk';

const url = process.argv[2]?.trim();

if (!url) {
  writeError('Missing website URL argument.');
  process.exit(1);
}

if (!process.env.ANTHROPIC_API_KEY) {
  writeError('ANTHROPIC_API_KEY is not set.');
  process.exit(1);
}

const projectRoot = process.env.WEBANALYZE_PROJECT_ROOT || process.cwd();
const maxTurns = Number.parseInt(process.env.WEBANALYZE_MAX_TURNS || '20', 10);
const maxBudgetUsd = Number.parseFloat(process.env.WEBANALYZE_MAX_BUDGET_USD || '1.25');
const model = process.env.WEBANALYZE_MODEL || undefined;

const prompt = [
  'Use the klicklocal-webanalyze skill to analyze this business website.',
  'Run the single-site workflow and return the complete lead report using the exact markdown template from the skill.',
  '',
  `Website URL: ${url}`,
  '',
  `Budget guardrails: finish within ${maxTurns} agent turns and $${maxBudgetUsd} USD.`,
  '- Prefer WebFetch over heavy browser automation when HTML checks are enough.',
  '- Do not repeat the same checks.',
  '- Steps 8, 9 and 11a are mandatory. If near the limit, run their minimum versions (one web search each) instead of skipping.',
  '- Your final message MUST be the full markdown report only.',
].join('\n');

try {
  let reportMarkdown = '';
  let sessionId = null;
  let durationMs = null;
  let resolvedModel = model ?? null;
  let totalCostUsd = null;
  let numTurns = null;
  const errors = [];

  const queryOptions = {
    cwd: projectRoot,
    settingSources: ['project'],
    skills: ['klicklocal-webanalyze'],
    systemPrompt: { type: 'preset', preset: 'claude_code' },
    tools: { type: 'preset', preset: 'claude_code' },
    permissionMode: 'bypassPermissions',
    allowDangerouslySkipPermissions: true,
    maxTurns,
    maxBudgetUsd,
    effort: 'medium',
    ...(model ? { model } : {}),
    env: {
      ...process.env,
      ANTHROPIC_API_KEY: process.env.ANTHROPIC_API_KEY,
    },
    stderr: (data) => {
      process.stderr.write(data);
    },
  };

  for await (const message of query({
    prompt,
    options: queryOptions,
  })) {
    if (message.type === 'system' && message.subtype === 'init') {
      sessionId = message.session_id;
      resolvedModel = message.model ?? resolvedModel;
    }

    if (message.type === 'assistant') {
      const textBlocks = (message.message?.content ?? [])
        .filter((block) => block.type === 'text')
        .map((block) => block.text)
        .join('\n')
        .trim();

      if (textBlocks) {
        reportMarkdown = textBlocks;
      }
    }

    if (message.type === 'result') {
      durationMs = message.duration_ms ?? durationMs;
      totalCostUsd = message.total_cost_usd ?? totalCostUsd;
      numTurns = message.num_turns ?? numTurns;

      if (message.subtype === 'success') {
        if (message.result?.trim()) {
          reportMarkdown = message.result.trim();
        }

        writeJson(buildPayload({
          success: true,
          reportMarkdown,
          sessionId: message.session_id ?? sessionId,
          durationMs,
          resolvedModel,
          totalCostUsd,
          numTurns,
        }));
        process.exit(0);
      }

      errors.push(...(message.errors ?? []));
      writeJson(buildPayload({
        success: false,
        reportMarkdown,
        sessionId: message.session_id ?? sessionId,
        durationMs,
        resolvedModel,
        totalCostUsd,
        numTurns,
        errors: errors.length ? errors : [`Agent run failed: ${message.subtype}`],
      }));
      process.exit(reportMarkdown ? 3 : 2);
    }
  }

  writeJson(buildPayload({
    success: false,
    reportMarkdown,
    sessionId,
    durationMs,
    resolvedModel,
    totalCostUsd,
    numTurns,
    errors: ['Agent finished without a result message.'],
  }));
  process.exit(reportMarkdown ? 3 : 2);
} catch (error) {
  writeError(error instanceof Error ? error.message : String(error));
  process.exit(1);
}

function buildPayload({
  success,
  reportMarkdown,
  sessionId,
  durationMs,
  resolvedModel,
  totalCostUsd,
  numTurns,
  errors = [],
}) {
  return {
    success,
    report_markdown: reportMarkdown,
    session_id: sessionId,
    duration_ms: durationMs,
    model: resolvedModel,
    total_cost_usd: totalCostUsd,
    num_turns: numTurns,
    errors,
  };
}

function writeJson(payload) {
  process.stdout.write(`${JSON.stringify(payload)}\n`);
}

function writeError(message) {
  writeJson({ success: false, errors: [message] });
}
