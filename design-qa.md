# RideDock v0.1.0 – design verification

Date: 2026-10-05. Selected direction: the first displayed design, a quiet cockpit with persistent navigation and focused content sections.

## Visual evidence

- Source visual: `/Users/oliverwand/.codex/generated_images/01a10b19-9f13-7ce1-afeb-d49f27ca2d52/exec-ec40fdc7-4d80-494d-a33a-2ab4b4641118.png`.
- Evidence directory: `/Users/oliverwand/.codex/visualizations/2026/10/05/01a10b19-9f13-7ce1-afeb-d49f27ca2d52/ridedock-implementation/`.
- Latest implementation: `01-fahrt-desktop.png`; state: authenticated ride detail, Overview active, route collapsed, no rating selected.
- Source: 1487 × 1058 pixels. Implementation: 1488 × 1056 pixels at a 1488 × 1056 CSS viewport. Both are compared at one image pixel per CSS/design pixel; the contact sheet preserves their original dimensions without resampling. The one-pixel width and two-pixel height difference is retained.
- Full-view comparison: `16-designvergleich.png`. Focused content/controls comparison: `17-detailvergleich.png`.
- Additional captures: overview, Coach, Insights, training settings, connections and archive (`02`–`07`); mobile overview, ride, Coach, Roadmap and settings (`08`–`13`); authentication (`14`–`15`); sharp native-width preview (`18-fahrt-vorschau.png`).
- Responsive checks: 1488 × 1056 desktop, 1024 × 900 tablet, 390 × 844 mobile and 320 × 720 narrow mobile. Temporary viewport overrides were reset; native preview is 510 × 968.

The in-app browser softens screenshots taken with a wide simulated viewport. Typography and control geometry were also checked through rendered DOM/computed styles and sharp mobile/native captures; desktop antialiasing cannot be judged precisely from the simulated capture.

## Comparison

- **Typography:** Hanken Grotesk throughout. Shared page headings are 44 px desktop / 30 px mobile; section headings 20 px, subordinate headings 16 px, control/metadata text 14 px. Buttons are 44 px high with 14 px labels. SVG chart labels retain 12 px at their actual rendered dimensions.
- **Layout and spacing:** persistent pale sidebar, white main canvas, contextual Back link/breadcrumb, three ride metrics, underlined section navigation and elevation/profile beside rating on desktop. Narrow layouts stack the content. Related explanations and route details expand on demand.
- **Colors and surfaces:** dark ink, restrained gray dividers and green active navigation/actions. Primary text-on-green controls use the darker green token; surfaces avoid the previous collection of competing boxes. Focus rings, active selections and loading/error states remain distinct.
- **Assets and icons:** existing RideDock brand asset and licensed Tabler SVG icons. The elevation profile and route are generated from actual recordings. No fabricated search, avatar or illustration assets were added.
- **Copy:** German authenticated screens and English guest screens were checked. Labels name the actual action, including separate rating/note saves. Insights copy points to the Routes & archive filter rather than a selector above the training section.
- **Responsive/accessibility:** no document overflow at the tested widths, including the open 320 px menu. Numeric rating choices retain 44 px targets; day selectors stack on mobile. Keyboard focus, menu Escape, skip link, labeled fields and reduced-motion styles are present.

Production adaptations to the reference: actual ride values replace example values; the elevation axis uses recorded time; the header omits the mock's unsupported search/account controls; ratings use two rows of practical 44 px choices. A shared type scale and component system extend the chosen direction across the other screens.

## Interaction verification

- Archive → ride → contextual Back retains period and page and restores a scrolled list position when session storage is available.
- Ride sections and Coach sections support direct links and browser Back/Forward.
- Selecting a Coach day exposes and focuses its details on mobile; browser Back restores the preceding selection; closing details returns to the agenda.
- Settings availability switches correctly, and submitting an invalid field from another section exposes and focuses that field without saving.
- Rating, notes, comparison groups, profile and zone saves preserve their section/context in integration tests. Analysis requests return to the Coach section.
- Route expansion renders map tiles at the correct size. Insights heatmap works after opening its initially hidden section, expanding/collapsing and resizing while hidden.
- Login/registration navigation and field layouts were checked; no account was created. The original local authenticated preview was restored.
- Browser console checked: the hidden heatmap's zero-width canvas error was fixed; rechecking the affected flow produced no new errors. Live AI/provider submissions were not made; existing integration tests exercise those paths.

## Comparison history and fixes

1. **P2 – small/dense controls and inconsistent headings:** centralized the type/button scale and replaced narrow rating choices with 44 px controls. Evidence: latest desktop ride and mobile ride captures.
2. **P2 – unreadable mobile day columns and verbose Coach panel:** replaced the weekly columns with an agenda; collapsed cautions/data gaps. Evidence: `03-coach-desktop.png` and `10-coach-mobil.png` (desktop capture predates the final collapsed cautions; mobile capture includes them).
3. **P2 – chart labels distorted by a fixed SVG viewBox:** redraw charts at actual container dimensions, reduce mobile tick count and use 12 px labels. Evidence: latest `09-fahrt-mobil.png` and DOM viewBox checks.
4. **P2 – mobile Roadmap document overflow:** establish a containing block for phase content. Before: `12-roadmap-mobil-vor-korrektur.png`; after: `12-roadmap-mobil.png` and document width equals viewport width.
5. **P1 – heatmap initialization inside a hidden section:** defer view/heat-layer setup until the container is visible and observe valid size changes. Post-fix: a nonzero 350 × 380 canvas, loaded tiles and no new console errors after section/resize/expand interactions.
6. **P2 – save/validation losing section context:** preserve section fragments after saves/analysis requests and expose the first invalid hidden field. Browser checks and navigation integration tests pass.

## Result

No actionable P0/P1/P2 findings remain. Screenshot softness at wide simulated viewports is a capture limitation; inspect the live preview for fine desktop rendering. Future polish can tune individual chart density with more varied recordings.

Validation: 140 PHP tests, 27 JavaScript tests, PHPStan, PHP_CodeSniffer, EditorConfig, ESLint, formatter check and production build passed. README stays within its existing product/technical sections; the dated changelog records v0.1.0 with no Unreleased entry.

final result: passed
