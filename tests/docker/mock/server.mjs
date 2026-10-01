/**
 * Local stand-in for the WPistic platform used by the SEOistic docker test stack.
 *
 * Implements the exact public contracts the plugin speaks:
 *   WPistic       POST /api/v1/licenses/{activate,validate,deactivate}
 *   Legacy        POST /wp-json/licenseistic/v1/license/{activate,ping,deactivate}
 *   AI gateway    POST /v1/license/chat   → proxied to the host's Ollama so the
 *                                            metered path runs a real local model.
 *
 * Credits start at 1,000 (agency demo allocation) and drain per the plugin's own
 * cost table. If Ollama is unreachable the gateway falls back to canned content
 * so UI flows still work offline.
 */
import http from 'node:http';

const PORT = Number(process.env.PORT || 8088);
const OLLAMA_URL = process.env.OLLAMA_URL || 'http://host.docker.internal:11434';
const OLLAMA_MODEL = process.env.OLLAMA_MODEL || 'qwen2.5:3b-instruct';

const CREDIT_COSTS = {
  title: 1,
  description: 1,
  keywords: 1,
  alt: 1,
  optimize_content: 3,
  full_optimize: 5,
  schema: 2,
  aeo_audit: 10,
};

const credits = { left: 1000, plan: 'agency', resets: '1st' };

const LICENSE_DATA = {
  status: 'active',
  expires_at: '2027-12-31 23:59:59',
  product_id: 1,
  plan: 'agency',
  seats: 5,
};

const CANONICAL_LICENSE_DATA = {
  valid: true,
  status: 'active',
  product: 'seoistic',
  plan: 'agency',
  expires_at: '2027-12-31 23:59:59',
  activation: { id: 'test-activation', domain: 'example.com', environment: 'production' },
  entitlements: { 'seoistic.sites.max': 5 },
  updates: { channel: 'stable', allowed: true },
  check_after: 43200,
  grace_period_days: 7,
  signature: 'test-signature',
  activation_token: 'test-activation-token-1234567890',
  verification_key: 'test-verification-key-1234567890',
};

function send(res, status, body) {
  res.writeHead(status, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(body));
}

function readBody(req) {
  return new Promise((resolve) => {
    let raw = '';
    req.on('data', (chunk) => { raw += chunk; });
    req.on('end', () => {
      try { resolve(JSON.parse(raw || '{}')); } catch { resolve({}); }
    });
  });
}

async function ollamaChat(messages, temperature, maxTokens) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 90_000);
  try {
    const resp = await fetch(`${OLLAMA_URL}/api/chat`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: OLLAMA_MODEL,
        messages,
        stream: false,
        options: {
          temperature: typeof temperature === 'number' ? temperature : 0.4,
          num_predict: Math.max(64, Math.min(2048, Number(maxTokens) || 600)),
        },
      }),
      signal: controller.signal,
    });
    if (!resp.ok) throw new Error(`ollama http ${resp.status}`);
    const data = await resp.json();
    const content = data?.message?.content?.trim();
    if (!content) throw new Error('ollama empty message');
    return content;
  } finally {
    clearTimeout(timer);
  }
}

function cannedContent(task) {
  switch (task) {
    case 'description':
      return 'A practical local guide to testing WordPress SEO plugins end to end — scores, schema, sitemaps, and AI-assisted titles without touching production.';
    case 'keywords':
      return 'wordpress seo test, local docker wordpress, seo plugin e2e, schema test, sitemap check';
    case 'alt':
      return 'Animated SEO score ring showing 92 out of 100 inside the SEOistic dashboard';
    case 'schema':
      return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BlogPosting',
        headline: 'Testing SEOistic locally',
        description: 'End-to-end plugin testing with docker.',
      }, null, 2);
    case 'aeo_audit':
      return JSON.stringify({
        verdict: 'answer-ready',
        strengths: ['Clear question-style headings', 'Direct answer paragraph after each heading'],
        gaps: ['Add a short TL;DR block at the top'],
      }, null, 2);
    default:
      return 'Testing SEOistic locally: A Complete Guide (2026 Edition)';
  }
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const path = url.pathname;

  if (path.startsWith('/api/v1/licenses/')) {
    const action = path.split('/').pop();
    await readBody(req);
    if (action === 'activate' || action === 'validate') {
      return send(res, 200, CANONICAL_LICENSE_DATA);
    }
    if (action === 'deactivate') {
      return send(res, 200, { deactivated: true });
    }
    return send(res, 404, { error: { code: 'not_found', message: 'unknown action' } });
  }

  if (path.startsWith('/wp-json/licenseistic/v1/license/')) {
    const action = path.split('/').pop();
    await readBody(req); // consume the request body
    if (action === 'activate' || action === 'ping') {
      return send(res, 200, { success: true, message: 'ok', data: LICENSE_DATA });
    }
    if (action === 'deactivate') {
      return send(res, 200, { success: true, message: 'ok' });
    }
    return send(res, 404, { success: false, message: 'unknown action' });
  }

  if (path === '/v1/license/chat' && req.method === 'POST') {
    const body = await readBody(req);
    const task = String(body?.task || 'title');
    const messages = Array.isArray(body?.payload?.messages) ? body.payload.messages : [];
    const cost = CREDIT_COSTS[task] ?? 1;

    let content;
    let provider = 'ollama';
    try {
      content = await ollamaChat(
        messages,
        body?.payload?.temperature,
        body?.payload?.max_tokens
      );
      credits.left = Math.max(0, credits.left - cost);
    } catch (error) {
      console.error(`[mock] ollama failed for task=${task}: ${error.message} — serving canned content`);
      content = cannedContent(task);
      provider = 'canned';
    }

    return send(res, 200, {
      data: content,
      provider,
      credits: {
        ...credits,
        charged: provider === 'ollama' ? cost : 0,
        recent: [{ task, credits: provider === 'ollama' ? cost : 0, created_at: Math.floor(Date.now() / 1000) }],
      },
    });
  }

  if (path === '/healthz') {
    return send(res, 200, { ok: true, model: OLLAMA_MODEL, credits_left: credits.left });
  }

  send(res, 404, { error: 'not found', path });
});

server.listen(PORT, () => {
  console.log(`[mock] WPistic stand-in listening on :${PORT} → ollama ${OLLAMA_URL} (${OLLAMA_MODEL})`);
});
