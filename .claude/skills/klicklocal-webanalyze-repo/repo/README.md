# klicklocal-webanalyze

Claude skill for scored website lead analysis and competitor research (KlickLocal).

Produces a lead report with:
- Overall score X/100 across 6 categories (Technik, SEO, Content & Vertrauen, Conversion, Marketing-Reife, Eigenständigkeit)
- Strengths, issues, and prioritized improvement areas
- SEO assessment with subscore
- NAP/contact extraction (Impressum) for CRM
- Local market potential and 3/6-month growth forecast with financial value (range-based, assumptions stated)
- Competitor comparison mode (multi-site scoring table + verdict)

## Install
Upload `release/klicklocal-webanalyze.skill` in Claude (Settings → Skills), or place the `SKILL.md` in your skills directory.

## Structure
- `SKILL.md` — the skill definition
- `evals/evals.json` — test prompts used during development
- `release/` — packaged `.skill` file
