# Saty Guesthouse Tax Presentation — design & code

**Tax Burden Analysis and Pricing–Tax Proposals for a Small Guesthouse in Saty Village, Kazakhstan**
Student: Nguyen Xuan Mai · Tourism Business / Taxation · 2026

| File | What it is |
|---|---|
| `Saty_Guesthouse_Tax_Presentation.pptx` | The deck: 9 slides, 16:9, editable native charts and tables, speaker notes on every slide |
| `Saty_Guesthouse_Tax_Presentation.pdf` | PDF export for printing or a backup copy |
| `build.js` | The generator (pptxgenjs). All slide content, layouts and design tokens live here |
| `lib/apply_theme.js` | Writes the theme colours into the .pptx after the build |
| `assets/saty-landscape.jpg` | Generated mountain-and-lake illustration on the title slide (made in `build.js`) |

## Rebuild

```bash
cd presentation
npm install
npm run build        # -> Saty_Guesthouse_Tax_Presentation.pptx
```

To use a real photo of Saty or Kolsai Lakes on slide 1, replace `assets/saty-landscape.jpg` in PowerPoint (right-click → Change Picture), or point `bg` in `build.js` at your own file.

## Design system

| Token | Value | Use |
|---|---|---|
| Forest green (`dk2` / `accent1`) | `#1F4D3A` | Titles, table headers, key callouts. The main colour |
| Cream (`lt2`) | `#F6F1E7` | Background of content slides |
| Warm beige (`accent3`) | `#E4D6B8` | Borders, icon circles |
| Muted gold (`accent2`) | `#B8963E` | Accent: number badges, highlights on dark backgrounds |
| Gold tint | `#F2E8CF` | Qualification and note callouts |
| Sage (`accent4`) | `#6E8B74` | Secondary map elements |
| Brick (`accent5`) | `#9A4A36` | Negative effects (demand fall, VAT threshold) |
| Ink (`dk1`) | `#1E2A24` | Body text |
| Dark background | `#13261F` | Title and closing slides |

- **Type:** Georgia for headings and big numbers (theme heading font), Arial for body text (theme body font). Titles are 26 pt bold, body text 12–15 pt, captions and sources 10–11 pt.
- **Structure:** dark slides open and close the deck, with light cream slides in between. Every content slide has an "action title" (the topic) and a subtitle that states the finding.
- **Repeated motifs:** gold numbered badges, soft white cards with a light shadow on cream, and icons inside beige circles.
- **Layouts:** `TITLE_DARK`, `CONTENT` (title and subtitle placeholders, footer, slide number) and `CLOSING_DARK`. The slides are split into sections: Introduction, Analysis, International experience, and Recommendations and conclusion.

UX principles applied (refactoring-ui and UX heuristics): clear hierarchy through size, weight and colour rather than borders; one message per slide; numbers shown as large stat callouts; legible contrast on every background; consistent spacing (0.25–0.3 in gutters, 0.6 in margins); and the same styling for every chart.

## Slide map

1. Title (with student name)
2. Enterprise profile, location map (schematic), research objectives, key question
3. Legal framework: table of 9 taxes and the 4 key 2026 changes
4. Financial model: revenue formula, regime comparison table and chart, break-even margin of 40%
5. VAT: before/after stacked price chart, elasticity effect (−4.28%), VAT-threshold bar (occupancy 47.4%)
6. International comparison: Bhutan, Japan and France
7. Kazakhstan's tourist contribution: timeline, exemption, 2-night calculation, lessons
8. Recommendations: A (guesthouse) and B (Kazakhstan), expected effects
9. Conclusion, final statement, 10 references

Two analytical additions derived from the given numbers:
- **Break-even margin 40%:** the simplified regime (4% of revenue) is cheaper than 10% PIT only when profit ÷ revenue is above 40%.
- **VAT trigger at about 47.4% occupancy:** 43.25 M KZT ÷ (10 rooms × 365 × 25,000 KZT) means occupancy above about 47.4% crosses the mandatory VAT threshold.
