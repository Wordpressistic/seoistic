# Image SEO / Alt Text Guidance

## Alt text rules
- Describe the image literally and specifically, as if to someone who cannot see it — not a keyword restatement of the page topic.
- Keep it concise: under 125 characters is a practical target for screen-reader usability as well as SEO.
- Include the focus keyword only when it genuinely and naturally describes what's in the image — never force it in when it doesn't fit.
- Purely decorative images (spacers, background flourishes) should have an empty `alt=""`, not a fabricated description — this is also a core accessibility requirement, not just SEO.
- Do not start alt text with "Image of..." or "Picture of..." — screen readers already announce it as an image; it's redundant.

## Filename and context
- Descriptive, hyphenated filenames (`red-leather-hiking-boots.jpg` rather than `IMG_2931.jpg`) help image search relevance, though this matters less than alt text and surrounding context.
- Images placed near closely related text rank better in image search than images floating with no contextual copy nearby.

## Technical basics worth flagging (not this generator's job to fix, but worth noting)
- Serve appropriately sized images (no 4000px-wide image displayed at 400px) — affects Core Web Vitals (LCP) more than rankings directly, but both matter.
- Use modern formats (WebP/AVIF) with fallbacks where the platform supports it.
- Always set explicit width/height (or aspect-ratio) to prevent layout shift (CLS).

## When generating alt text for a specific image
Base the description on: the page title, surrounding content/context, and the image's apparent subject if inferable from filename or caption — be honest that without seeing the actual pixels, the description should stay generic-but-accurate rather than inventing specific visual details that may be wrong.
