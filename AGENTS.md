# Repository Agent Instructions

## Blade loops

- Do not use MaryUI components, including `<x-mary-*>`, inside Blade loops such as `@foreach`, `@forelse`, and `@while`.
- Use native HTML styled with DaisyUI and Tailwind CSS classes for buttons, icons, badges, inputs, tables, and other elements rendered inside loops.
- MaryUI components may still be used outside loops.
- When modifying an existing loop that contains MaryUI components, replace the MaryUI components in the touched loop with DaisyUI-native markup.
