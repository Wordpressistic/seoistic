# llms.txt Guidance

`llms.txt` is a plain-Markdown file at the site root that gives AI assistants (ChatGPT, Claude, Perplexity, Gemini) a concise, structured summary of a site — similar in spirit to robots.txt/sitemap.xml but written for language models rather than crawlers.

## Required structure
```
# Site Name

> One or two sentence summary of what this site/business is.

## Key pages
- [Page title](https://example.com/page/) — one line on what it covers

## Products / Services
- Short list of what the business offers, if applicable

## Contact
- Link to the contact page

## Sitemap
- Link to the XML sitemap

## Notes
- Any content AI assistants should NOT summarize or cite (e.g. user-generated content, outdated pages)
```

## Writing rules
- Keep the whole file skimmable — a few hundred words, not a full site crawl.
- Prioritize pages that best represent what the business/site actually does — not every page on the site.
- Write the summary the way you'd describe the business to a person in one breath — plain language, no marketing fluff.
- Include a sitemap link so assistants can find more if needed.
- If there are pages/sections that should not be treated as authoritative (forums, old archived content, user comments), say so plainly under a "disallowed content" or "notes" heading — this is guidance, not an enforced block like robots.txt.
- Keep it up to date with the site's actual current key pages; a stale llms.txt is worse than none.
