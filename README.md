# Modularity Table of Contents

A Modularity module that automatically generates a dynamic, linked table of contents based on headings found on the page. Features a mobile-optimized drawer interface and optional sticky sidebar positioning.

## Description

This plugin provides a Table of Contents module for the Modularity plugin ecosystem. It automatically scans the page content and generates a navigable list of headings, making it easier for users to quickly jump to different sections of long-form content.

## Features

- **Automatic Heading Detection**: Automatically finds and lists headings from the page content
- **Customizable Heading Levels**: Choose which heading levels (H2, H3, H4) to include in the table of contents
- **Multiple Container Support**: Scans specific content areas including:
  - Sidebar content areas
  - Article content
  - Main content area
- **Card Display Option**: Option to display the TOC inside a styled card component
- **Smart Card Filtering**: Option to ignore headings inside cards except for card headers
- **Smooth Navigation**: Clickable links that navigate smoothly to each section
- **Active State Tracking**: Highlights the current section as users scroll through the page
- **Mobile Drawer Interface**: On mobile devices (below 78em/1248px), TOC appears in a slide-out drawer with a fixed toggle button
- **Sliding Track Indicator**: Visual indicator that follows the active section
- **Sticky Sidebar Support**: Option to make the entire sidebar column sticky when TOC is present
- **Responsive Design**: Works seamlessly across all device sizes with dedicated mobile and desktop UX

## Configuration Options

### Module Instance Settings

Configure these settings for each individual TOC module instance:

1. **Include headings from following sidebars**: 
- **Content area (above article)**
- **Content area (below article)**
- **Main container bottom**
2. **Which heading levels to include**: Choose from H2, H3, and/or H4 headings (default: H2)
3. **Place in card**: Toggle to display the TOC within a card component
4. **Ignore card sub-headers**: Option to exclude headings inside cards (except card headers)

### Global Settings

Configure these settings once for all TOC modules (found in Settings > Table of Contents):

1. **Show sliding track**: Display a visual sliding track indicator that follows the active section (default: enabled)
2. **Hide on mobile**: Hide the TOC module completely on mobile devices below 78em
3. **Sticky list**: Make the entire sidebar column sticky when it contains a TOC module

## CSS Variables

The module uses the following CSS custom properties that can be customized in your theme:

### Mobile Drawer
- `--modularity-toc-mobile-handle-bg`: Background color of the mobile toggle button (default: `var(--c-button-primary-color, #333)`)
- `--modularity-toc-mobile-handle-color`: Text/icon color of the mobile toggle button (default: `var(--c-button-primary-color-contrasting, #fff)`)
- `--modularity-toc-mobile-handle-bg-hover`: Background color on hover (default: `var(--c-button-primary-color-hover, #222)`)

### Sliding Track
- `--toc-border-width`: Width of the sliding track border (default: 4px)
- `--toc-track-color`: Color of the active sliding indicator (default: #666)
- `--toc-border-color`: Color of the background track (default: #ccc)
- `--toc-track-top`: Calculated top position of the sliding indicator
- `--toc-track-height`: Calculated height of the sliding indicator

### Sticky Positioning
- `--sticky-sidebar-top`: Top offset for sticky sidebar (default: `calc(50px + var(--wp-admin--admin-bar--height, 0px))`)
- `--header-height`: Height of the sticky header (used when body has `sticky-header` class)

### General
- `--base`: Base spacing unit (default: 8px)

## Example CSS Customization

```css
/* Customize mobile drawer button colors */
.modularity-mod-toc {
  --modularity-toc-mobile-handle-bg: #0073aa;
  --modularity-toc-mobile-handle-color: #ffffff;
  --modularity-toc-mobile-handle-bg-hover: #005a87;
}

/* Customize sliding track appearance */
.modularity-mod-toc {
  --toc-border-width: 3px;
  --toc-track-color: #0073aa;
  --toc-border-color: #e0e0e0;
}

/* Adjust sticky sidebar offset */
:root {
  --sticky-sidebar-top: 100px;
}
```

## Requirements

- WordPress
- [Modularity](https://github.com/helsingborg-stad/modularity) plugin
- [Municipio](https://github.com/helsingborg-stad/municipio) theme (v6.0.0 or higher)
- Advanced Custom Fields (ACF) Pro

## Installation

1. Clone or download this repository to your WordPress plugins directory
2. Run `composer install` to install dependencies
3. Run `npm install && npm run build` to build assets
4. Activate the plugin through the WordPress admin panel
5. The "Table of Contents" module will now be available in Modularity

## Usage

1. Edit a page or post where Modularity is enabled
2. Add a new module and select "Table of Contents"
3. Configure the module settings according to your needs
4. Publish or update the page
5. The table of contents will automatically populate based on the headings found on the page

## Mobile Behavior

Below the 78em (1248px) breakpoint, the TOC transforms into a mobile-optimized experience:

- A fixed toggle button appears on the right side of the screen
- Clicking opens a slide-out drawer from the right
- Drawer includes a close button and overlay for dismissal
- Focus is trapped within the drawer for accessibility
- Escape key closes the drawer
- Respects sticky header positioning when present

## Accessibility Features

- **Keyboard Navigation**: Full keyboard support with Tab/Shift+Tab navigation
- **Focus Trap**: Focus is trapped within the mobile drawer when open
- **ARIA Attributes**: Proper ARIA labels, roles, and states for screen readers
- **Escape Key**: Close drawer with Escape key
- **Focus Management**: Returns focus to trigger button when drawer closes

## Development

### Build Assets

```bash
npm install
npm run build
```

### Development Mode

```bash
npm run dev
```

### Watch Mode (rebuild on changes)

```bash
npm run watch
```

### Build Development Version (unminified)

```bash
npm run build:dev
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

- **Author**: Consid Borås AB
- **Repository**: [alingsas-kommun/modularity-toc](https://github.com/alingsas-kommun/modularity-toc)
