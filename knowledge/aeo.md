# AI Search Visibility / Answer Engine Optimization (AEO) Guidance

AI answer engines (ChatGPT browsing/search, Perplexity, Google AI Overviews, Gemini) don't rank pages the way traditional search does — they extract, synthesize, and cite. Optimizing for them means making a page easy to extract a correct, quotable answer from.

## What helps AI engines cite a page
- A clear, direct answer to the implied question near the top of the content — not buried after paragraphs of preamble.
- Genuine structure: descriptive headings, short paragraphs, and lists that map to sub-questions a reader (or model) would have.
- Concrete facts, numbers, and specifics — models prefer citing specific claims over vague marketing language.
- FAQ sections with real, literal question/answer pairs (and matching FAQPage schema) — these are easy for models to extract verbatim.
- A clear byline/author and publish/update date — recency and authorship are used as trust signals.
- An llms.txt file summarizing the site (see llms-txt guidance) — an explicit, low-noise description some crawlers use directly.

## What hurts AI visibility
- Content that requires heavy JS interaction to reveal (accordions that hide the actual answer, content behind clicks) — many AI crawlers do not execute JS.
- Vague, marketing-toned copy with no concrete facts to extract ("industry-leading", "best-in-class" with no substantiation).
- Duplicate or near-duplicate content across many pages — dilutes which page a model would cite.
- Missing or generic titles/meta descriptions — these are still used as the "preview" signal even for AI-driven results.

## Practical recommendations to give
When asked to "optimize for AI search," recommend concrete, page-specific changes: add a direct-answer paragraph near the top, add/expand a genuine FAQ section, tighten headings to match real sub-questions, add specific facts/numbers, and ensure the page is reachable without JS-gated content.
