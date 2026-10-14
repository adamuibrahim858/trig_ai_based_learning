# AI Coding Agent Prompt — Import Trigonometry Curriculum into Laravel Database

## Objective
Read and study `secondary_school_trigonometry_curriculum.md` from beginning to end. Then inspect the existing Laravel backend and database implementation and import the curriculum as structured, learner-accessible content. The React frontend should display the content using the existing API and learning-progression rules.

## Critical instructions
1. **Inspect before changing anything.** Read the curriculum file, `backend/README.md` (or the actual backend README path), any API integration guide, relevant migrations, models, controllers, services, routes, seeders, factories, tests, and the existing lesson/topic/assessment database structure.
2. **Use the real schema.** Identify the existing tables and relationships for courses, weeks/topics, lessons, lesson sections/content blocks, exercises, assessments, questions, and answers. Do not assume table or column names. Do not create duplicate tables or duplicate curriculum records.
3. **Plan the mapping.** Before implementation, produce a concise mapping of each curriculum entity and content block to existing tables/fields. If a required capability genuinely does not exist, implement the smallest compatible migration/model/API change and document it.
4. **Structure content by category.** Preserve this hierarchy:
   - Course: Secondary School Trigonometry
   - Week 1: Foundation
   - Week 2: Right-Angled Triangles
   - Week 3: Pythagoras' Theorem
   - Week 4: Trigonometric Ratios
   - Week 5: Finding Unknown Sides
   - Week 6: Finding Unknown Angles
   - Week 7: Elevation, Depression, Heights and Distances
   - Week 8: Integration and Consolidation
   - Lessons, sections, worked examples, formulae, checks, practice exercises, summaries, remedial guidance, and enrichment content under the appropriate week/lesson.
5. **Preserve the source.** Do not omit lessons or rewrite mathematics casually. Keep lesson order, definitions, worked examples, units, and practice questions. If correcting a confirmed mathematical error, document the correction in the final report.
6. **Assessment answers must remain protected.** Learners may see teaching examples and designated practice explanations, but do not expose mastery assessment answer keys before submission. Follow existing backend answer-masking and assessment-attempt design.
7. **Progression remains backend-authoritative.** Do not unlock the next lesson/week from frontend state alone. Preserve the platform's existing pass threshold (if configured as 70%), attempt limits, score calculation, assessment eligibility, and certificate logic. Confirm these from code/configuration rather than assuming them.
8. **Use safe, repeatable seed/import logic.** Prefer a dedicated, idempotent seeder or import command. Running it twice must not create duplicate courses, weeks, lessons, content blocks, questions, or options. Use stable unique keys/slug/order fields or other schema-supported identifiers. Do not delete unrelated production or learner data.
9. **Content rendering.** Store formulae and prose in the representation supported by the current application. If Markdown/rich text is rendered, ensure it is sanitized. If KaTeX is already supported, use its established formula syntax and avoid exposing unsafe raw HTML.
10. **Do not expose secrets.** Never place Gemini/API keys in frontend code or seed data. AI requests must use the existing Laravel proxy/service.
11. **Maintain project conventions.** Reuse existing architecture, naming, authorization, validation, transactions, logging, and API response formats. Avoid broad refactors unrelated to curriculum import.

## Implementation tasks
1. Find and read `secondary_school_trigonometry_curriculum.md`.
2. Inspect the current backend schema and the React pages/API calls that retrieve topics and lessons.
3. Produce a brief schema/content mapping and identify missing fields or relationships.
4. Implement an idempotent import/seed process for all eight weeks and all lessons/content in the Markdown file.
5. Associate each lesson with the correct week/category and stable sequence order.
6. Import lesson content as structured sections/blocks when supported: introduction, objectives, explanation, definitions, worked examples, formulas, common mistakes, quick checks, practice questions, summary, remedial activities, and enrichment activities.
7. Keep teacher-only answer keys and mastery assessment answers protected. If the current assessment system has a separate question bank, follow its established format and access controls. Do not accidentally turn every illustrative practice question into a graded exam question.
8. Verify the API returns the correct ordered hierarchy for an authenticated learner, including locked/unlocked state and any prerequisite information the frontend needs.
9. Verify React can display lesson content and formulas without malformed markup, missing content, duplicate records, or console/runtime errors. If the frontend already implements these views, preserve and test them; implement only the confirmed missing pieces.
10. Add or update automated tests for import idempotency, relationships/order, learner authorization, answer-key protection, API serialization, and backend-controlled progression.
11. Run relevant tests, seed/import against the appropriate local development/test database, and report actual command output. Never run destructive commands against production. Do not run migrations or seeders against a live database without explicit authorization.

## Required checks
- All eight weeks exist and appear in the intended order.
- All lessons in the curriculum hierarchy exist under the correct week and in the intended order.
- Lesson content has not been silently truncated.
- All examples and practice material are attached to the correct lesson/category.
- Re-running the import does not duplicate records.
- A learner can retrieve permitted content through the authenticated API.
- Unauthorized requests receive the application's expected 401/403 response.
- Mastery answer keys are not returned before assessment submission.
- Locked lessons cannot be accessed by bypassing the frontend.
- Passing/failing scores and attempt limits are still enforced by the backend.
- Existing data unrelated to this curriculum remains unchanged.
- React displays the returned content correctly on mobile and desktop.

## Execution rules
- Do not stop after writing a plan. Inspect, implement, test, and fix confirmed issues.
- Do not claim a feature works unless you tested it or clearly label it untested.
- If blocked by missing configuration, schema, credentials, or an unavailable service, continue with safe work that can be completed and clearly report the blocker.
- Keep changes focused and preserve existing working features.
- Do not overwrite the Markdown curriculum.
- Do not invent APIs or database fields without inspecting the repository first.

## Final report
Provide:
1. Files inspected.
2. Actual database tables/models/relationships used.
3. Content mapping and any migration added.
4. Number of weeks, lessons, and content items imported.
5. Seeder/import command and how to run it safely.
6. API routes used or added.
7. Frontend changes made.
8. Tests executed and their real results.
9. Confirmation of idempotency and assessment answer protection.
10. Any remaining blockers or items not tested.

Use these status labels for each item: `Implemented and tested`, `Implemented but not fully tested`, `Partially implemented`, `Blocked`, or `Not implemented`.
