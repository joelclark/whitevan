
# Flooring Interview — Logic Tree & Architecture

Now that the AI can scope out the job through the PDF, we need to ask the user some questions about the job so we can determine what things need to be priced out.  This is all still flooring specific even though we have plans to introduce additional trades down the line.  **The architecture below is not multi-trade aware, so make sure you don't repeat that mistake.**  (As estimate is for a particular trade.  Our first one is Flooring in this project.)

We will add the price-out in another phase.

## Previous Architecture

- **`Question` abstract class** (`app/Interviews/Question.php`) — just `key`, `label`, `type='select'`, `options`, optional `help`, `shouldAsk($answers)` (default true), `formatAnswer()`. Plain PHP, no AI.
- **`RoomInterview` / `LongTailInterview`** — static `questions()` returns an ordered array; `nextQuestionFor($model)` walks it, skipping answered ones and ones whose `shouldAsk()` is false, returning the first unanswered or `null`.

## Room Phase (Per Room, Order Matters)

1. **`material`** — LVP / Laminate / Engineered / Solid Hardwood / Tile / Carpet / Sheet Vinyl
2. **`existing`** — Bare / Carpet / LVP / Laminate / Hardwood / Tile / Sheet Vinyl
3. **`subfloor`** — None / Minor Patch / Major Self-Level
4. **`furniture`** — Empty / Move & Replace / Heavy Items
5. **`heavy_count`** — 1…5+ — **Conditional**: Only Asked When `furniture === 'heavy'` (Only Dependency in the Whole Tree So Far, More to Come)

## Long-Tail Phase (Once All Rooms Done, Per Estimate)

1. **`demo_haul_away`** — None / Van / Dumpster
2. **`baseboards`** — Leave / Remove & Reinstall / Remove & Replace
3. **`quarter_round`** — None / New / Reuse
4. **`transitions`** — 0…5+ (Job-Wide Total)
5. **`door_undercuts`** — 0…5+
6. **`toilet_pulls`** — 0…5+

## Dependency tree

```
Room:  material → existing → subfloor → furniture ─┬─ empty         → done
                                                   ├─ move_replace  → done
                                                   └─ heavy         → heavy_count → done

LongTail:  demo_haul_away → baseboards → quarter_round → transitions → door_undercuts → toilet_pulls → done
```

Only one conditional branch exists (`heavy_count`). Everything else is linear so far. Adding a question = new class in `Room/` or `LongTail/` + append to that interview's `questions()` array; phase transitions happen automatically. Answers flow into `QuoteBuilder` (`app/Quotes/`) which maps them to `LineItem`s.

ultrathink


---

Implement floorplan asset extraction for estimate PDFs using the page numbers already returned by the AI extraction flow.

  Requirements:

  - Do not change the AI agent contract to return images, base64, or binary file content.
  - Use the existing saved room-level page values to determine which PDF pages should be extracted.
  - Keep AI responsible for interpretation and PHP responsible for deterministic PDF processing.
  - Add a PHP-side service and/or queued job that takes the stored PDF and extracts either:
      - a preview image for each relevant page, or
      - a single-page PDF for each relevant page.
  - Use a server-side tool appropriate for PDF extraction/rendering such as pdftoppm, ImageMagick, or qpdf.
  - Persist the generated asset path(s) so they can be returned by the app and shown in the UI.
  - Design for one or more relevant pages per estimate.
  - Keep this extraction step separate from the AI inference step so it can be retried independently and debugged cleanly.
  - Follow existing Laravel app conventions, use the current queue / persistence patterns in the project, and add tests for the new behavior.

  Deliverables:

  - backend implementation
  - any required persistence changes
  - controller / response updates needed to expose the extracted asset URLs
  - minimal UI wiring to display the extracted floorplan asset
  - tests covering successful extraction and failure handling

