# UI and navigation fixes

Updated 17 September 2026 for the five role designs supplied by the user: [admin](https://www.figma.com/design/m9EZDG6TpxnaBCFbHCiyQn/Design?node-id=37-2), [member](https://www.figma.com/design/m9EZDG6TpxnaBCFbHCiyQn/Design?node-id=45-2), [sponsor](https://www.figma.com/design/m9EZDG6TpxnaBCFbHCiyQn/Design?node-id=367-261), [sponsor liaison](https://www.figma.com/design/m9EZDG6TpxnaBCFbHCiyQn/Design?node-id=138-2), and [moderator](https://www.figma.com/design/m9EZDG6TpxnaBCFbHCiyQn/Design?node-id=89-148).

## Changes

| Area | Result |
| --- | --- |
| Shared styling | Local Inter variable font, consistent heading sizes, panel spacing, dashboard card treatments and a shared 68px desktop navigation height. Regular page titles use 22px; dashboard greetings use 32px. |
| Figma assets | Original exported logo and bell SVGs. Logo preserves the 47.86:64 aspect ratio at 20.94 × 28px; bell uses 24 × 24px. No recreation or SVG path modification. |
| Navigation | All five roles have account menus, mobile menu controls and consistent active states. Sponsor and liaison paths now respect the `/mithra` application prefix. |
| Responsive layout | Rules for wrapping navigation, stacking cards and detail panels, scrolling tables, and keeping dialogs within the viewport. Browser verification is still required. |
| Moderator | Numeric IDs now agree with route constraints. Each queue links to the selected detail record; missing records return 404. |
| Bookings | Borrower/lender tabs load the corresponding database records. Details show the selected booking and recorded status; unknown or unrelated bookings return 404. |
| Sponsor liaison | Sponsor and purchase details, search/filter controls, aid status tabs, sample approved-grants CSV, and report print controls have valid destinations. |
| Admin appointments | Candidate selection and review use server-rendered links with the selected division/member. Confirmation remains disabled until its backend exists. |
| Dialogs | Corrected close controls, backdrop handling, accessible titles and record context. |
| Incomplete actions | Unsupported submissions are clearly marked as previews and disabled. Existing working Items forms remain intact. No fake success states or placeholder saves were added. |

## Verification

- `python tests/ui-navigation.py`: **877 read-only checks passed across 88 GET routes**, covering HTML wiring, selected records, filters, tabs and error cases.
- `python scripts/audit-ui.py`: **215 of 215 discovered link/asset targets returned HTTP 200**. Of 88 registered GET routes tested, 87 returned 200; `/items/1/edit` returned the expected ownership 403.
- Static form inspection found **zero unsupported rendered form destinations**, down from 72 occurrences targeting 51 destinations in the baseline. Most were converted to explicit previews; this does not mean their business workflows were implemented.
- PHP router checks passed for **93 route registrations**.
- All **151 PHP files** and **5 JavaScript files** passed syntax checks.
- Conventions check passed with no hard violations and 14 existing SQL advisories.
- Verification did not submit forms or change database records.

See [latest HTTP evidence](UI_AUDIT_RECHECK.json), [historical audit](UI_AUDIT.md) and [Figma frame inventory](FIGMA_SCREEN_INVENTORY.md).

## Remaining limits

Figma dashboard screenshots and source dimensions were inspected, but browser control was unavailable. No rendered application screenshot comparison, responsive viewport interaction, keyboard walkthrough or print-dialog test was possible. Pixel-level parity across all 101 inventoried Figma frames is **not verified**.

Most non-Items write workflows, booking lifecycle actions, login and role enforcement still need backend implementation. Sponsor/moderator/liaison screens include sample records; the liaison CSV is explicitly a demo export. The UI now communicates those limits instead of submitting to missing routes.

When browser access is available, compare all five dashboards at 1440 × 1024, inspect layouts at tablet/mobile widths, and exercise account menus, keyboard focus, dialogs, no-JavaScript navigation and report printing.
