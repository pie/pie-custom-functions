# Repository Coding Standards

## PHP Files

* Avoid using `empty()`; prefer stricter checks like `isset()` and `!== ''`.
* Do not nest multiple `dirname()` calls to traverse directories. Instead, use a central `definitions.php` file in the project root to store shared paths (e.g., enqueue paths/URIs).
* When enqueuing scripts and styles, use `filemtime()` to generate version query strings for cache busting.
<!-- * Use spaces within brackets and function calls: `[ 'key' => 'value' ]` and `function( $arg )`.
* Use Yoda conditions to prevent accidental assignments: `if ( 5 === $value )`. -->

## JavaScript Files

* Always use strict equality (`===`) and strict inequality (`!==`).
* Use `const` for values that never change and `let` for variables that can be reassigned. Avoid `var`.
* Use Yoda conditions (e.g., `if ( 5 === value )`).
* Prefer array methods like `.map()`, `.filter()`, and `.reduce()` over traditional `for` loops when applicable.

## SCSS Files

### 1. File Structure & Organization

* **Consistent naming:** e.g., `page-services.scss` → `page-newname.scss`.
* **Organize by type/folder:**

  * Patterns: `src/scss/patterns/`
  * Block styles: `src/scss/block-styles/`
  * Block SCSS: `src/blocks/[block-name]/styles.scss` & `editor.scss`
* **Entry points:** Ensure `styles.scss`, `editor.scss`, and `admin.scss` are included in build and enqueue.
* **Use `@use`** to import only what is needed.
* Keep shared variables/mixins in centralized SCSS files and import them via `@use` with namespaces.
* Avoid duplicate code by referencing shared patterns and mixins.
* Keep block-level SCSS as self-contained as possible.

### 2. Coding Standards

* **Nesting:** Parent → children → modifiers → pseudo-classes → pseudo-elements.
* **Responsive styles:** Nest media queries inside components using `@include mix.respond-to(breakpoint)`.
* **Namespacing:** Always use namespace prefixes like `vars.*`, `mix.*`, `typ.*`, `space.*`.
* **Spacing:**

  * One blank line between breakpoints.
  * One blank line between nested groups.
  * No blank lines between properties.
* **Variables:** Use functions/mixins rather than repeated/hardcoded values.
* **Modifiers:** Use `&--modifier` for BEM-style modifier classes.
* **Pseudo-elements/classes:** Always nest under the element they modify.
* **Units:** Use `rem` for spacing, typography, border radius, gaps, and layout values.
* **Imports:** Use namespaced imports like `@use "../globals/variables.scss" as vars;`.

## Custom Gutenberg Blocks

* Follow general JavaScript and PHP coding standards above.
* Build the blocks having in mind that they will be styled with BEM methodology.
* If there are other blocks in the src/blocks/ directory, follow their established structure.
* In `edit.js`, keep code modular using separate component, hook, and utility files stored in a block's `/utils` directory.
* Use custom SVG icon components from `src/components/custom-svg-icons.js` instead of `@wordpress/icons`.
* In `render.php` (server-side rendering):

  * Escape output using `esc_html()`, `esc_url()`, etc.
  * Sanitize all dynamic data.
  * Follow repository PHP coding standards.
  * Move template markup into a separate file under the block's `utils/frontend-partials.php`; only include structure there—logic stays in `render.php`.
* If React is used on the frontend, ensure `view.js` shares components, hooks, and utilities with `edit.js` to avoid duplication and pass the attributes through a script in `render.php`.
* Place editor-only components/utilities in files not imported by `view.js`.
* Ensure all required attributes are defined in `block.json`.
* Make sure editor preview markup matches the frontend HTML structure to share the same styles.
* Place editor-only styles in `editor.scss` (e.g., placeholders, inline controls).