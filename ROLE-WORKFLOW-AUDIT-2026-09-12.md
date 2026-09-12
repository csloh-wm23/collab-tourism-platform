# TourLingo role and workflow audit — 12 September 2026

**Current status:** the confirmed-defect implementation and targeted regression pass is complete. See **Implementation pass — 12 September 2026** at the end for fixes, evidence and remaining limits. Earlier sections are a chronological record of the pre-fix audit, not current release status.

## Verdict and scope

Not ready for an end-to-end completion claim. Pages and backend handlers exist, but several workflows stop before the recipient can act or the user can recover/manage the result.

**Audit handoff:** the role/module functional review and final targeted closure checks are recorded below. This document is the input to the implementation pass, not a website release approval. New closure findings are tablet registration overflow, weak light primary-button contrast, and mobile drawer focus escape. No application fixes were made in this audit.

**Latest correction:** finding 2 (broken public-question handoff) is withdrawn. The full browser workflow passed; the earlier synthetic test overlooked a capture-phase event handler. Historical raw assertion totals below include two invalid failure assertions and must not be presented as five real application failures. Confirmed privacy/storage and failed-write feedback defects are unaffected.

This is an audit, not an implementation pass. Existing application changes and request.json were preserved. The initial inspection did not submit changes; subsequent write tests use only the isolated database/site documented below. Browser login sessions were switched using documented demo accounts. Login success can reset existing login-failure counters. The original Chrome administrator page was left untouched during isolated testing.

Evidence labels below distinguish live browser observations from code-confirmed behavior. No claim is made that every write action, hardware voice workflow or responsive breakpoint was retested.

## Initial role coverage (later executed checks supersede these limitations)

| Role/page | Live inspection | Backend trace / limitation |
|---|---|---|
| Guest | Translator-only navigation; login and registration pages | Unauthenticated private APIs denied access; assistance/glossary APIs remain public |
| Tourist | Successful demo login, overview, journey, preferences/privacy, assistant; restaurant guide loaded | Traced profile, records, consent, pack and deletion paths; no destructive or profile-changing submissions |
| Approved business | Studio profile and inbox; existing new/in-progress/answered questions | Traced profile/content CRUD, inbox updates, visitor reports and public-page queries; no content edits |
| Pending business | Successful login, application status and resubmission form; studio absent | Resubmission and approved-only content gates traced, not submitted |
| Rejected business | Successful login, Needs revision status and resubmission form | No rejection reason displayed or stored by approval handler |
| Editor | Successful login, Insights, filters, no Administration navigation | Editor/admin gate traced; direct admin API browser navigation was blocked by browser tooling, not proof of application denial |
| Administrator | Existing signed-in session, Insights, Administration, queue, summaries and export links | Approval transaction and exports inspected, not executed |
| Suspended tourist | Login rejected with “This account is suspended.” | Existing-session suspension behavior needs dedicated regression coverage |
| Public business visitor | Business information, language selector, approved phrase, question form, empty-state sections | Actual question → inbox → answer → reply display passed in later browser test; language fallback still needs clearer labeling |

## Priority 1 — repair before calling workflows complete

### 1. Unclear reports have no individual review/resolution workflow

- Evidence: `api/report.php` writes NULL reporter/source/translation and a source hash; `api/insights.php` aggregates issues. Browser shows summaries only. Notes are accepted but not shown in the summary query.
- Impact: editors/admins cannot inspect the exact translation, user note, correct it, assign a review status, or return a response.
- Proposed fix: explicitly opt in to sharing report text; add protected editor/admin report detail and reviewed/resolved state. Decide whether corrections are review notes or reusable published content. Do not imply they retrain Google.
- Acceptance: submit a disposable report, see text and note as editor/admin, deny unrelated roles, resolve it, verify status and counts after reload. Old reports must say text was not retained.

### 2. WITHDRAWN: public question handoff works in the actual browser flow

- The original conclusion overlooked a capture-phase listener later in `business.php`. It calls `stopImmediatePropagation()`, submits with `requires_reply:true`, persists the returned token and exposes a reply card. The older `onclick` handler is not the executed user flow.
- Browser verification: submitted `AUDIT visitor form question` through the public form; refreshed business inbox; answered it there; clicked Check for reply on the visitor page; saw `AUDIT business reply received` and Answered by the business.
- Earlier HTTP assertions sending `requires_reply:false` remain valid API observations, but are **invalid assertions of a broken UI handoff**. Do not count them as application defects.
- Cleanup opportunity: remove redundant old handler to avoid misleading future maintenance. Remaining risks: token storage retains only the latest question per business; network/storage failures need dedicated tests. Do not redesign a workflow that already works.

### 3. Personal browser storage is not separated by account or fully deleted

- Evidence: `assets/js/app.js` uses constant keys `tourlingo_conversation_v1`, `tourlingo_emergency_card_v1` and `jomcommunicate_records_v3`. Login/logout does not namespace them. Conversation data loads for any authenticated user. `persistConversation()` does not check history consent.
- `deleteJourneyData` clears records/privacy/jompack keys but not conversation or emergency-card keys or their in-memory state.
- Impact: another account on the same browser can inherit personal local content; “delete journey data” leaves conversation/emergency information behind; disabling saved history does not prevent timeline persistence.
- Proposed fix: account-scoped storage, explicit device-only emergency-card policy, consistent history consent, complete cleanup and safe treatment of legacy shared keys.
- Acceptance: account A saves test content; account B sees none; opt-out prevents persistence; deletion removes all related local and server data without deleting another account's data.

### 4. Saved original messages are truncated

- Evidence: `api/records.php` saves `mb_substr($title, 0, 150)` while the translator accepts 500 characters. Saved-message opening restores `row.title` as the original input, paired with the full stored translation.
- Impact: after reloading, a long saved message can show a partial source with a full translation, including in large-screen mode.
- Proposed fix: retain the full original in a dedicated field or adequately sized column; keep a separate short display title if useful.
- Acceptance: save/reload/open a 500-character message and compare both texts exactly. Existing truncated text cannot be recovered automatically.

### 5. Two-way quick replies can claim the wrong source language

- Evidence: `renderTwoWay()` always inserts English quick replies (“Yes, please.” etc.), then sets the source language to `current.to`.
- Impact: after English → BM, clicking a reply sends English text labelled as BM. The same problem occurs with other non-English reply languages.
- Proposed fix: localize replies to the actual next-speaker language or explicitly translate their known English source; retain a consistent conversation direction.
- Acceptance: test EN↔BM and EN↔Chinese quick replies, not only manual typed replies.

### 6. Pending translation results can overwrite newer input state

- Evidence: the main `translate()` response always assigns `current` and renders it; the exact-text guard only protects the spelling-detection helper. Input remains editable while translation and history/analytics requests are in flight.
- Impact: a slow old response can appear beside a newer message. Editing during later awaits can null/change `current`, affecting tracking or next-speaker handling.
- Proposed fix: request identity/version guard and immutable response snapshot; suppress stale rendering and post-response actions.
- Acceptance: deliberately slow a request, edit text/change language/open a saved message, and ensure only the current request can update the result or conversation.

## Priority 2 — complete misleading or disconnected behavior

### 7. Insights filters apply to different datasets inconsistently

- Live reproduction: editor Insights, Location = `Audit-no-such-location`, Apply filters. Usage events became empty, but translations stayed 18, issues stayed 3, and repeated questions/business-category rows remained. Filter was reset afterward.
- Evidence: event queries apply location and business type; report/record queries do not. Interaction queries omit location and scenario. CSV shares the same data preparation.
- Fix: apply meaningful filters consistently, or visibly identify which widgets are unfiltered/not applicable. “Translations” currently counts saved translation records, not all translation requests; label or change this definition. Also distinguish metrics derived from saved history from opt-in analytics.
- Acceptance: each filter has documented scope, matching screen/export values and meaningful empty states.

### 8. Business rejection has no reason or detailed review screen

- Live: rejected account sees Needs revision and editable details, but no explanation. Admin queue shows name/category/email and Approve/Reject, without the full application detail.
- Evidence: `api/admin.php` stores decision/status/audit data but no rejection reason.
- Fix: show full application details to reviewer and require/store/display a useful rejection reason.
- Acceptance: reviewer rejects with note → owner sees note → corrects/resubmits → queue returns to pending with clear history.

### 9. Destination-pack management is incomplete

- Evidence: My journey renders destination-pack summaries with Remove only, not Open/Use. Remove deletes the database link but does not remove the cached `jompack:` content. Cache fallback exists, but there is no service worker/offline app shell in the project.
- Impact: “saved” does not provide a direct reopen path; removed packs may still be used from local cache. Cached phrases in an already loaded page are not the same as a website that can start fully offline.
- Fix: Open/Use action with correct destination/scenario/language, aligned local/server deletion, and honest offline scope. A full offline app requires a separate scoped decision.
- Acceptance: save/reload/open/remove the same pack; verify cache removal and explicitly test supported offline behavior.

### 10. Saved tourist preferences are not carried into assistant needs

- Live: tourist profile shows Halal food and Peanuts; assistant dietary/allergy fields are blank.
- Evidence: `loadProfile()` fills profile and journey fields, not assistant dietary/allergy/emergency fields. It provides recommendations but does not prefill the assistant from those stored preferences.
- Fix: apply saved defaults on initial load without overwriting edits made in the current session. Decide how emergency contact/details should be included.
- Acceptance: saved preferences appear in the next visit's assistant request and remain editable.

### 11. Public business language fallback is silent

- Live: selected Bahasa Malaysia, but description/services/payment/facility information is English; some language-specific sections are empty.
- Evidence: public render chooses requested translation, then English, then base business fields, without a fallback label.
- Fix: explicitly show the available fallback language or require translated content before claiming availability; retain useful content without mislabelling it.
- Acceptance: an unavailable translation shows a clear fallback notice and the studio preview matches the public page.

### 12. Several failure paths falsely appear successful

- Evidence: `saveRecord()` returns on HTTP failure; Save's caller still toasts “Phrase saved as a favourite.” Favorite/delete handlers update the list without checking response status. Pack removal also ignores failure. Public question success has the same problem (item 2). Report prompt cancellation is converted to empty notes and still submits.
- Fix: return/throw explicit results, update UI only on success, preserve data on failure, distinguish cancel from empty notes.
- Acceptance: simulate 403/500/network failure for each write and confirm no false success, disappearance, or unexpected submission.

### 13. Guest-only translation is enforced in navigation, not all feature APIs

- Live HTTP without cookies: records 401; tourist 403; business 403; profile 401; insights 403; admin 403. Assistance and glossary returned 200.
- Fix: confirm the intended boundary, then guard non-public feature endpoints accordingly. Keep public business information intentionally accessible.
- Acceptance: unauthenticated role matrix at both UI and API level, including reporting with a guest CSRF session.

### 14. Recommendations do not constitute an editorial improvement workflow

- Evidence: Insights produces recommendation text, while global phrase-pack/glossary APIs offer no editor management actions. Business owners can edit their own content, but editors cannot implement global recommendations in the application.
- Classification: a requirement/product decision, not proof that analytics is broken. Proposal recommendations are present; claiming a fully closed improvement cycle would be inaccurate.
- Decision: either describe recommendations as advisory with a manual maintenance process, or explicitly scope editor content management and publication into the final implementation.

## Additional items for the regression pass

- Two-way mode clears the input for the next speaker; the newly added automatic-language spelling suggestion is then cleared too. Decide whether correction should happen before advancing the turn.
- Speech normalization already silently rewrites selected cappuccino variants and biases recognition alternatives. Audit this against the new optional-only spelling correction expectation; do not claim general spelling/grammar correction or guaranteed voice accuracy.
- Emergency translation fallback uses a generic Malay phrase that omits detailed user needs when the provider fails. Make the fallback and untranslated details unmistakable; do not demonstrate a real emergency call.
- Stored-history list limits (8 recent / 20 favourites shown) need clear navigation/wording if complete history management is claimed.
- Public-business queries do not join owner active status. Confirm intended visibility if an already-approved owner's account becomes suspended.
- README still describes some earlier UI behavior and overstates complete deletion/offline support. Update it only after the implementation is verified.

## Checks run and what they establish

- PASS: 102 application checks (`tests/run.php`). Many are source-string/feature-marker checks; they do not establish end-to-end completeness.
- PASS: frontend helper tests (`tests/frontend.js`). They do not exercise full browser workflows or network failure handling.
- PASS: database schema/seed checks (`tests/database.php`), read-only against the current database.
- PASS: syntax validation for all project PHP files, app.js and core.js.
- PASS/observed: documented tourist, pending, rejected and editor logins; approved-business and administrator sessions; suspended login denial; guest UI restriction; restaurant guide loading; role page rendering; public-page language fallback; inconsistent Insights location filtering.
- NOT RUN: new account registration, password change, picture replacement, deletion, approve/reject/resubmit, publishing, visitor question/answer writes, report resolution (absent), live microphone accuracy, real Google translation/TTS calls, export download/content verification, all mobile/tablet breakpoints, offline reload. These require the final regression pass with disposable data and explicit acceptance checks.

## Proposed final implementation sequence

1. Privacy/storage/long-message integrity and stale-result handling.
2. Preserve the working visitor question → business inbox → visitor reply path; simplify duplicate handlers and test truthful error states.
3. Consented unclear-report → editor/admin review → resolution; agree correction/publication scope first.
4. Quick replies, saved packs, preference integration, rejection feedback and fallback labels.
5. Filter/metric definitions, role API gates and documentation.
6. End-to-end tests using disposable fixtures, reload/cross-account checks, failure injection, real voice/provider checks and responsive review. Only then commit/push the approved implementation and give a final completion statement.

## Extended execution pass — 12 September 2026

This section supersedes the earlier NOT RUN list only for checks explicitly executed below. This is **not an exhaustive browser sign-off**. Application source was not changed during this pass.

### Isolation and evidence

- A source snapshot runs at `http://127.0.0.1:8097` against fresh database `tourlingo_audit_20260912_full`, with a separate session name/path, origin, uploads and browser storage. No working-site accounts or records were modified.
- Reproducible HTTP test scripts and JSON results are in ignored `tmp/full-audit/`: `integration.mjs`, `remaining.mjs`, `extra.mjs` and their result files. These are one-run mutation scripts against disposable fixtures, not idempotent production tests.
- Initial results with unwritable XAMPP session storage were discarded as invalid test setup. After session/upload temporary paths were moved into the audit directory, authentication worked.
- Historical raw HTTP runs: **132 assertions, 127 passed, 5 failed**. Two failure assertions were subsequently withdrawn because the synthetic question payload did not match the browser handler. The three valid failures cover source truncation and repeated checks of the missing demo tourist profile; they are not three separate defects.

### Executed HTTP coverage

| Area | Executed outcome |
| --- | --- |
| Roles | Tourist, approved/pending/rejected business, editor and admin login; suspended denial; records/tourist/business/insights/admin GET matrix across all roles and guest passed. |
| Authentication | Tourist/business registration, duplicate email rejection, prohibited admin registration, new login, password change, old/wrong password denial, new password success, five-failure lockout and logout passed. |
| Request protection | Invalid CSRF denied on records, tourist, profile, preferences, business, admin and reports. |
| Profile | Account update, valid PNG upload, fake image rejection, travel preferences write/read and account preservation after journey deletion passed. Image replacement/oversized/dimension boundary cases still need checks. |
| Personal records | Save, favourite, delete and cross-account mutation isolation passed. **FAIL:** 300-character source retained only 150 characters. |
| Privacy | Server history opt-out and analytics consent false/true behavior passed. Browser storage privacy remains defective. |
| Journey | Add/remove API actions passed. Newly registered traveller's Hotel pack appeared in browser and persisted. Fresh seeded demo traveller GET produced warnings and `profile:false` (see finding 15). |
| Business content | Phrase/FAQ/term create, read, edit, toggle and delete responses passed. Another business could not delete an owner's phrase. Translated public profile save and public/private visibility passed. |
| Business verification | Pending publishing denied; reject → applicant status → resubmit → approve → studio access without re-login passed. Newly registered business begins pending. |
| Visitor questions | Initial synthetic payload test omitted the capture-handler flag; not an actual UI failure. Full public form → inbox → answer → visitor display later passed. Invalid reference denied. |
| Reports | Valid unclear-report submission accepted. No detailed review/resolution UI exists, so submission is not an end-to-end review pass. |
| Exports | Admin CSV/PDF and editor Insights CSV returned successful responses and expected content type/PDF signature. PDF pagination/rendering and complete exported-value reconciliation remain unverified. |
| Assistance | All six seeded Malaysia/BM situation endpoints returned success. |
| Translation | Same-language shortcut passed; >500-character input rejected. Actual Google translation attempted in browser but failed with provider-contact error in isolated environment. |

### Executed browser checks in this pass

- Guest translator-only interface and registration invitation displayed correctly.
- Mobile 390×844 light translator and dark login inspected; selected theme persisted between pages and after login. Tablet 768×1024 profile inspected in light mode; settled layout had no document horizontal overflow. These samples do not establish every page/breakpoint/theme combination.
- Newly registered traveller: overview → assistant → Hotel selection → guide build → save → My journey displayed saved Hotel pack. Saved pack still offers Remove but no Open action.
- Profile Travel preferences button scrolled the section below the sticky navigation after animation settled.
- Same-language successful translation with conversation mode OFF left timeline empty when mode was subsequently enabled. With mode ON, translation created a conversation entry. Edit restored source/output; large-screen dialog displayed both. This validates state/UI flow, not cross-language provider accuracy.
- **Critical browser reproduction of finding 3:** traveller created `Audit same-language message.`, logged out through UI, editor logged in on the same origin, then enabled conversation mode. The traveller's entry appeared in the editor's timeline. Server records remained separate. This is client-storage leakage between accounts.
- Report prompt cancellation was attempted, but the in-app browser exposed no dialog handle; cancellation behavior is code-reviewed, not browser-verified.

### 15. Fresh demo tourist account lacks its required profile row

- `scripts/seed_demo_accounts.php` inserts tourist users but does not create `tourist_profiles` rows. The independent schema import plus this seed produces a logged-in demo tourist without a profile.
- `api/tourist.php` GET uses an inner join, receives false, then accesses profile offsets. With warnings enabled this corrupts JSON; without warnings the response still contains `profile:false`.
- Pack writes succeeded; their failed retrieval assertion was caused by the malformed profile response, not a failed pack insert. A newly registered traveller with a proper profile row passed persistence checks.
- Fix: create missing role-specific rows idempotently in demo setup and handle missing profile data defensively in the endpoint. Verify both fresh install and existing-account migration. Do not reset existing preferences.

### Remaining work before claiming complete coverage

- Desktop/tablet/mobile and both themes for every role-specific tab, public page, registration, all modal/empty/error states; keyboard focus/cursors and contrast measurements.
- Injected 403/500/disconnection tests for save/delete/favourite/pack/report/public question actions; delayed-translation races.
- Browser verification of consent withdrawal, full journey deletion, emergency card cross-account isolation, offline reload, saved-item reopen/retry/copy and all content-publication UI paths.
- Actual microphone recognition, start/finish timing and audible playback require microphone participation; provider translation/TTS need a reachable provider. Emergency calling must not be live-tested.
- Full upload boundaries/replacement and export rendering/value reconciliation.

The 102 existing application checks, frontend helper suite and database/schema checks were rerun and passed. These do not override the reproduced workflow failures or remaining coverage above.

### Post-crash continuation

- Recovered the existing isolated database and restarted the test server; did not re-seed or reset completed test data.
- Nine additional HTTP assertions passed (`tmp/full-audit/upload-boundaries.mjs` / `upload-results.json`): valid image replacement, new random filename, old test image removal, empty/oversized/SVG rejection, duplicate account email denial, empty speech rejection and session speech throttling. Aggregate HTTP count is now **141 assertions: 136 passed, 5 failed** (same repeated failures described above).
- Browser fault injection on the **copied** records endpoint forced HTTP 503 for writes; production source was not modified. Confirmed three failures: Save claimed “Phrase saved as a favourite”; favourite toggling appeared successful but reverted after reload; delete removed a row visually but it reappeared after reload. These are direct browser reproductions of finding 12. Removed fault injection after testing.
- Full role/page/theme responsive sweep, other write-failure paths, complete privacy deletion/offline checks, export rendering and real microphone/provider checks remain outstanding. Recovery and these additional checks are not a complete audit sign-off.

### Further continuation: privacy, exports and full visitor handoff

- **History consent FAIL, browser:** turned off Save translation history; received Privacy choices saved; translated `AUDIT history disabled marker` in two-way mode using the same-language endpoint; reloaded; enabled two-way mode; the marker remained in the timeline.
- **Deletion FAIL, executed source handler:** browser confirmation could not be controlled reliably. Instead, `deletion-handler.mjs` executes the unmodified extracted app handler with confirmed deletion and a successful mocked server response. Records/privacy/pack keys are removed, but `tourlingo_conversation_v1` and `tourlingo_emergency_card_v1` remain. This is a handler test, not a passed browser-confirmation test. Result saved as `deletion-results.json`.
- **Emergency card browser check:** saved fictional text and opened the saved card. The English side retained the text; the generic Malay fallback omitted it. No real emergency call made.
- **Business content browser PASS:** created Audit browser phrase through Content studio; found it in Content library and the public visitor page; clicked Hide; reloaded public page and confirmed it disappeared.
- **Visitor reply browser PASS / correction to finding 2:** actual form submission created a receipt/check-reply card. Refresh in Business studio showed the new question. Sending an answer and clicking Check for reply displayed the exact answer to the visitor. Both the original and test-copy source contain the capture-phase handler; this was an audit interpretation error, not a newly implemented fix.
- Business Public profile, Content studio, Content library, Question inbox and Visitor reports were opened. The inbox required its provided Refresh button to load a newly submitted external question.
- **PDF export inspected:** downloaded live audit admin CSV/PDF and corresponding JSON. Rendered both PDF pages with Poppler and inspected them. Summary values (6 active users, 2 approved businesses, 6 pending, 3 saved translations), role totals and pending list agreed between CSV/PDF and dataset. Tables are readable with no clipped rows in this fixture. Minor issue: Recent administrative activity heading is orphaned at the bottom of page 1 while its table starts on page 2.
- **Timezone clarity:** generated PDF time showed 04:15 while stored activity rows showed 09:56; CSV explicitly reports +02:00. Export generation inherits PHP's default timezone rather than specifying Malaysia or labeling timezone in the PDF. Normalize/display timezone before comparing chronology. This is environment-sensitive, not evidence that stored events changed.
- **Voice:** user reports testing it successfully, accepts occasional recognition mistakes. Record as user-tested; not agent-observed microphone/audible playback validation. Cross-language provider remained unreachable from the isolated server on the earlier attempt.
- **Responsive caveat:** several fast viewport/theme scans captured animated intermediate widths or the wrong selected tab and were discarded. Settled 390px profile and public-page screenshots were inspected; public light/dark mobile rendering had no horizontal document overflow. Do not turn discarded scans into a claim of all page/theme combinations passing. Full exhaustive responsive coverage remains outstanding.

### 16. Admin PDF section pagination and timezone clarity

- Keep a section heading with its table header and first row when deciding a page break.
- Use an explicit report timezone (and label it), consistently with activity timestamps.
- Verify with both short and long pending queues and administrative activity lists.

### Provider and race-condition follow-up

- Credential-free connectivity probe failed inside the restricted execution environment (cURL 7), then succeeded with approved network access (HTTP 404 at the provider root, normal for that URL). Restarted only the isolated loopback server with network access. No provider keys printed.
- Six live translation calls returned HTTP 200: English→BM, BM→English, Chinese automatic detection→English, English→Chinese, English→Indonesian and English→Thai. A BM TTS request returned HTTP 200, audio/mpeg, 11,136 bytes. Results: `tmp/full-audit/provider-results.json`. Prior provider-unreachable limitations are resolved for these samples; no general translation-quality guarantee is implied.
- Browser normal two-way flow passed: Saya mahu kopi ais. → I want iced coffee.; direction automatically became English→BM; English reply translated to BM and recorded under that direction. Unavailable Google confidence was not displayed.
- Finding 5 directly reproduced: after the English→BM reply, pressing Yes, please. created a timeline row with English text but declared Bahasa Malaysia→English and unchanged English output.
- Finding 6 directly reproduced with a four-second delay inserted into the copied translation endpoint: submitted I want tea., edited input to I want water. before completion; visible source was I want water. while output was Saya mahu teh. Removed the audit-only delay afterward. The working endpoint was not changed.

### Final continuation: admin workflow and regression checks

- Actual Administration UI approval passed for the disposable Audit New Business account: the application disappeared from the pending queue; approved businesses changed from 2 to 3 and pending from 6 to 5. These changed fixture counts must not be compared as a discrepancy with the earlier downloaded PDF.
- Repeated the Insights location-filter test as admin using AUDIT-NO-SUCH-LOCATION. Activity rows became empty and consented activity became zero, while translation/report totals and business interaction rows remained. This independently reproduces finding 7.
- Administration inspected at verified innerWidth values of 390, 768 and 1440, in light and dark themes. Sampled dashboard, queue and table layouts were readable with no horizontal document overflow. The Review applications shortcut reached the queue after scrolling settled. This is sampled visual coverage, not every control/error/modal state.
- Mobile Insights filters and summary controls were inspected in dark mode at 390px. Existing unfiltered admin tab was used for these visual checks; the older isolated desktop tab retains its deliberately empty location filter.
- Reran the existing suites: 102 application checks PASS; frontend conversation/confidence checks PASS; database/schema/seed checks PASS; administration PDF generation check PASS. Passing these suites does not cancel the reproduced integration failures.
- Corrected aggregate HTTP interpretation: **139 valid assertions: 136 passed, 3 failed**, after excluding the two invalid question-handoff assertions from the historical raw total of 141. The three failures represent two defects (source truncation and missing demo traveller profile). Browser and extracted-handler failures above are separate evidence, not folded into this count.

### Consolidated functional coverage and fix gate

| Role | Executed functional coverage | Important unresolved issue |
| --- | --- | --- |
| Guest | Translation, registration invitation, private API denial, authentication screens | Public assistance/glossary API policy differs from translator-only UI |
| Traveller | Registration/login/password, profile/preferences/uploads, consent, records, two-way/Edit/large display, guides and journey packs | Cross-account local data, consent/deletion, truncation, stale translation and quick replies |
| Approved business | Profile/locales/public visibility, content CRUD and browser publication/hiding, inbox answer and visitor reply retrieval | Failure feedback and locale fallback clarity; preference/content integration gaps |
| Pending/rejected business | Restricted publishing, status, reject/resubmit/approve and subsequent studio access | No actionable rejection reason |
| Editor | Login, Insights/filter behavior, export response and access restrictions | No individual unclear-report review/resolution or global-content editing workflow |
| Admin | Role gates, summaries, approval UI, audit trail, CSV/PDF download/rendering | Same report-review gap; filter definitions and PDF pagination/timezone |
| Suspended account | Login refusal | Existing-session suspension remains an explicit regression-test gap |
| Public visitor | Public profile/language, phrase publication/hiding, question → inbox → answer → displayed reply | Missing-locale fallback label and network-error recovery need attention |

The audit has identified enough confirmed failures to justify a focused implementation pass. It is **not** a clean-bill-of-health or exhaustive accessibility/responsive sign-off. Remaining coverage includes every theme/breakpoint/modal combination, existing-session suspension, true offline reload, all write-failure paths, image dimension limits, and comprehensive keyboard/contrast testing. No production implementation or commit/push was performed during this audit. Any report-review implementation must obtain explicit consent before retaining original/translated text and must not pretend to recover text absent from old reports or retrain the translation provider.

### Closure checks after the second crash

- `session-dimensions.mjs`: 14 assertions PASS. Existing traveller sessions lose records/journey access when suspended; an existing approved-business session receives only application status (`approved:false`, no phrases or inbox) and content writes return 403. The initial assumption that any business GET 200 was a security failure was incorrect and was replaced with payload/write checks. Fixture statuses restored in `finally`.
- Actual PNG uploads at 6000×1 accepted; 6001×1 and 1×6001 rejected. These complement the previously tested byte-size, file-type and replacement checks.
- **Offline loaded-page PASS / reopen FAIL:** saved Hotel/BM pack, stopped only the isolated server, rebuilt guide and observed Offline pack · Malaysia · Bahasa Malaysia with the saved phrases. Saving while disconnected raised an unhandled Failed to fetch error in the pack handler without a save-failure notice. Reload then reached the browser connection-refused error page, not the app. Browser tooling blocks inspecting that internal error-page URL, but the failed target and connection-refused outcome were returned. Server restarted after this deliberate test. No operating-system network settings were changed.
- Mobile 390px light login and expanded business registration inspected: usable single-column forms, no document overflow. At tablet width 768px the expanded business registration document was 820px wide, with clipped right-hand fields and an excessively broken promotional headline.

### 17. Tablet registration overflow

- Browser reproduction: registration → Tourism business, 768×1024 viewport, light mode; document width 820px. Right form edge is clipped and horizontal scrolling is required.
- Fix the authentication layout breakpoint and minimum-width constraints without redesigning desktop branding. Verify both registration roles and login at 390/768/1024/1440px, both themes.

### 18. Light-mode primary button text contrast

- Browser computed style on Create my TourLingo account: white text on rgb(15,174,154), 16px, font weight 760. Relative-luminance contrast is approximately 2.78:1. This is below both 4.5:1 normal-text and 3:1 large-text targets.
- Use a darker button surface or sufficiently dark text for shared light-mode primary actions, then measure default/hover/focus/disabled states separately. Do not assume every teal surface has the same contrast.

### 19. Mobile navigation drawer does not contain keyboard focus

- At 390px, opened the navigation drawer and pressed Tab repeatedly. Focus moved to contrastButton, account menu, profile section controls and form fields outside the sidebar while the drawer was open. Escape closes the drawer and returns focus to the menu control.
- Fix drawer focus entry/containment and make obscured background controls inert while open. Test Tab/Shift+Tab, Escape and restored focus. Contrast/keyboard observations are not a formal accessibility certification.

### Failure and keyboard closure evidence

- Executed the unmodified extracted report and public-question handlers (`failure-handlers.mjs`). Report HTTP 403/503 show failure messages; a rejected network request produces an uncaught error with no notice. Cancelling the report prompt still sends one request. Public-question HTTP 503 shows an error; a rejected network request produces an uncaught error with no notice. These are actual-source handler tests with mocked transport, not claims of browser prompt manipulation.
- Mobile large-text profile settled at document width 375px in a 390px viewport; its horizontal section-navigation strip is internally scrollable. Travel preferences mouse activation lands the heading below sticky controls. Discarded transient 502px measurement during resize.
- Saved communication opened both source and translation; large-screen modal opened at 390px dark mode. Tab stayed on the close button; Escape closed it and restored focus to Large-screen message. Copy displayed Copied, but browser clipboard readback was empty; exact OS clipboard contents remain unverified in this browser environment.
- Both mobile light and dark login inspected. All five approved-business tabs were activated in each theme at 390px: public profile, content studio, content library, inbox and visitor reports. DOM headings matched the tab and document width remained 375px. This checks rendering/geometry, not every possible user-supplied content length.

### Audit handoff — final coverage and limitations

- Completed all five business-studio tabs at 390, 768 and 1440px in both themes (30 tab/size/theme activations). Document width did not exceed the viewport in those checks. Settled tablet visitor-report screenshot was readable; screenshots captured during the short entrance fade are not contrast evidence.
- Public business tablet page inspected in both themes; no document overflow. Approved phrase opens its bilingual reply display. Existing visitor answer survived page navigation. Earlier desktop/mobile public checks and full question/answer workflow remain applicable.
- Registration at 768px overflows to 820px for both traveller and business choices in dark mode too. Mobile dark business form has no horizontal document overflow. This corroborates finding 17 rather than creating multiple duplicate issues.
- Backend integration totals after closure: **153 valid assertions, 150 passed, 3 failed** (two distinct known defects). Separate source-handler failure tests: 6 checks, 3 passed, 3 failed (report cancellation and disconnected report/public-question notices). Browser failures such as account storage leakage, stale translation and offline pack saving are separately documented, not hidden in this aggregate.
- Existing application and frontend suites pass. All production PHP files and frontend JavaScript syntax checked again; database/schema suite passes. No tests were changed to conceal application failures: corrections affected only ignored audit scripts whose original assumptions did not match the actual API/UI contract.
- Voice capture is user-tested and accepted with occasional recognition errors. No emergency number was called. Exact clipboard contents could not be independently verified through the in-app browser, despite its Copied notice. These are explicit manual-only limitations.
- This is broad functional review with representative visual/accessibility checks, not a formal accessibility certification, load test or exhaustive Cartesian test of every device, role, language, modal, network condition and content length. The remaining implementation work is the documented defects and requirement decisions, followed by targeted regression and release checks. Prior chronological NOT RUN lists are superseded only by the explicit evidence recorded later.

**Fix order:** (1) account-scoped local data/consent/deletion, (2) stale-result and quick-reply state, full saved source and honest error feedback, (3) consented editor/admin unclear-report review, (4) tablet registration/contrast/keyboard drawer, (5) packs/preferences/rejection feedback/filter/export consistency. Keep optional global content editing and full offline app installation separate from clear bug fixes unless requirements confirm them.

## Implementation pass — 12 September 2026

This section supersedes the earlier **unfixed** statuses for the items listed below. It does not turn the historical audit into an exhaustive all-devices certification.

### Implemented

- Findings 3–6: account-ID-scoped browser caches, legacy shared-cache eviction, consent-aware conversation persistence, complete current-account journey cache cleanup, full saved original text in metadata, localized two-way replies and request-version guards across translation/history/analytics awaits. Failed saves/deletes/favourites no longer produce false success or optimistic state changes.
- Findings 1/12: opt-in exact-text reporting, cancellation without submission, connection-error feedback, editor **and** admin individual review under Insights, status transitions, mandatory reviewer notes, audit history and stale-review conflict protection. Old/unshared reports explicitly state that text was not retained. Guest/suspended report access denied.
- Findings 8/15: actionable rejection reasons shown to the applicant, full address/owner details in the approval queue, suspended resubmission blocked, idempotent creation of missing traveller profiles, and corrected demo-profile seeding without overwriting existing profile values.
- Findings 9–11: saved-pack Open action displays the selected guide; relevant profile preferences prefill the assistant without overwriting fields the traveller edited; emergency fallback retains untranslated original details visibly; missing public locales are labelled as English fallbacks. Device-only pack saves report account-sync failure honestly, and the UI explains that offline use requires an already-open page.
- Findings 7/16: saved-record counts labelled Saved translations; resolved reports removed from needs-attention aggregates; UI/API/CSV disclose the dimensions each dataset actually supports. No invented location association for anonymous reports. PHP/MySQL timestamp display uses Malaysia time, exports label MYT, and PDF headings stay with their table header/first row.
- Findings 13/17–19: guest guide/glossary API gates, compact tablet authentication layout, darker light-mode primary buttons, mobile drawer background inertness, Tab wrapping and Escape focus restoration.
- Public visitor receipts now retain multiple questions rather than replacing the first. Both survive reload and earlier replies remain visible. Public business access additionally requires an active owner. Clipboard/playback failures and browser-storage failures receive actionable feedback.
- No schema reset or destructive data migration was needed. Production records and the untracked `request.json` were not edited. Browser upgrade deliberately discards old *unscoped* local caches because their owner cannot be established; server-saved records remain intact. Already truncated legacy originals cannot be recovered.

### Verification from this implementation pass

- 102 production-source/application checks PASS. Fixed the source scanner so ignored `tmp` snapshots cannot falsely satisfy checks. Updated the two obsolete structural expectations for immutable translation snapshots and consented report text; behavioural checks exercise both changes.
- Frontend core tests PASS; 22 actual-handler/state/failure regression checks PASS (stale responses, edits during history save, consent, cancelled/network-failed reports, failed record mutations, account-specific deletion, disconnected public submission).
- 41 isolated HTTP assertions PASS on the final run: role gates, 500-character source round trip, history consent, sharing/no-sharing, editor review, admin resolution, CSRF, concurrent review conflict, reject/correct/resubmit/approve, visitor answers, and filter/CSV scope.
- 14 existing-session suspension and image-dimension checks PASS. Six live provider translation directions/detection samples returned 200; BM speech API returned MP3 audio. Actual microphone recognition remains user-tested, not agent-audible verification.
- Database/schema/seed checks PASS; 31 PHP files lint PASS; JavaScript syntax PASS; diff whitespace checks PASS. PDF generation and downloaded CSV/PDF pass; rendered both pages with the activity heading correctly on page 2, including MYT generation time.
- Chrome browser (not embedded browser): EN→BM translation and BM quick reply→EN verified; saved guide Open verified; saved dietary/allergy/Penang/BM preferences verified; traveller→editor conversation isolation verified; editor review form save and retained history verified; public missing-locale label, two question receipts, reload and earlier answer verified.
- Registration (traveller/business) and login measured at 390/768/1024/1440px in both themes without horizontal document overflow. Inspected settled tablet light and desktop dark auth screenshots. Mobile dark report review and saved guide fit at 390px. Drawer Tab/Shift+Tab wrap and Escape/focus return verified. These are targeted regressions, not every modal/content combination.

### Explicitly not represented as bug fixes

- Finding 2 was withdrawn: the original visitor question-to-inbox workflow was already functional.
- Finding 14/global phrase-and-glossary CMS is not in the implemented proposal module requirements in README; business-owned content editing remains supported. A separate global CMS would be a new feature.
- A service-worker/installable offline website was not added. Cached phrase use is supported on an already-loaded page, with that limitation stated honestly.
- No guarantee of perfect speech recognition/translation, accessibility certification, or a fix to the Codex desktop application's crashes. No real emergency call made. Browser clipboard permission/OS contents remain a manual check; failure feedback is implemented.
