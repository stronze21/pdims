# Repository Agent Instructions

## Blade loops

- Do not use MaryUI components, including `<x-mary-*>`, inside Blade loops such as `@foreach`, `@forelse`, and `@while`.
- Use native HTML styled with DaisyUI and Tailwind CSS classes for buttons, icons, badges, inputs, tables, and other elements rendered inside loops.
- MaryUI components may still be used outside loops.
- When modifying an existing loop that contains MaryUI components, replace the MaryUI components in the touched loop with DaisyUI-native markup.

## IHoMIS Pharmacy synchronization

- After every source change made under `C:\laragon\www\emr2\pdims\ihomis_pharmacy`, immediately apply the same change, or an appropriate adaptation, to the main PDIMS application at `C:\laragon\www\emr2\pdims`.
- Do not wait for or request separate confirmation before synchronizing the change.
- Preserve differences in framework version, architecture, styling system, and file organization; do not blindly copy a file when the main application requires an adapted implementation.
- After synchronization, report which PDIMS files changed and identify any satellite-only change that has no corresponding main-application surface.
- Database safety restrictions remain in force during synchronization.
