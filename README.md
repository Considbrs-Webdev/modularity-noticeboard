# Modularity Noticeboard

A Modularity module that implements a municipal digital noticeboard for publishing legally binding public notices—meeting summons, agendas, adjusted minutes and decisions—replacing the physical noticeboard, managing appeal deadlines under municipal law, and providing accessible, auditable, and integratable publication workflows.

## Features

### Custom Post Type for Notices

The plugin registers a custom post type `noticeboard_notice` for managing notices. Each notice can contain:

- Title and content
- Publication date
- Archive/expiration date
- Attached PDF documents
- Protocol links

### Taxonomies

Two taxonomies are available for organizing notices:

- **Notice Types** (`noticeboard_notice_type`) – Categorize notices by type (e.g., meeting summons, agendas, decisions, minutes)
- **Notice Groups** (`notice_group`) – Assign notices to responsible groups or departments (e.g., municipal board, committees)

### Modularity Module

The Noticeboard module can be placed on any page using Modularity and offers the following display settings:

- **Display style** – Choose how notices are presented (e.g., card view)
- **Number of notices** – Limit how many notices to show
- **Filter by notice type** – Show only specific types of notices
- **Group by notice type** – Organize displayed notices by their type
- **Archive mode** – Display all notices (useful for archive pages)
- **Archive link button** – Add a link to the full noticeboard archive

### Archive Support

The plugin provides archive display capabilities:

- Built-in post type archive for notices
- Option to use a custom page as the archive
- Configurable URL slug for the post type
- Breadcrumb integration for navigation

## Requirements

- WordPress 5.0+
- [Modularity](https://github.com/helsingborg-stad/Modularity) plugin
- [Advanced Custom Fields PRO](https://www.advancedcustomfields.com/pro/)
- PHP 7.4+

Recommended:
- [Municipio](https://github.com/helsingborg-stad/municipio) theme (version 6.0.0+)

## Installation

1. Download or clone the plugin to your `/wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/considbrs-webdev/modularity-noticeboard.git
   ```

2. Install PHP dependencies:
   ```bash
   cd modularity-noticeboard
   composer install
   ```

3. Install JavaScript dependencies and build assets:
   ```bash
   npm install
   npm run build
   ```

4. Activate the plugin through the WordPress admin panel under **Plugins**.

5. Flush permalinks by visiting **Settings → Permalinks** and clicking "Save Changes".

## Configuration

### General Settings

Navigate to **Noticeboard → Settings** in the WordPress admin to configure:

- **Post type slug** – Customize the URL slug for notices (default: `notice`)
- **Custom archive page** – Optionally use a regular page as the noticeboard archive instead of the default post type archive
- **Archive page selection** – Choose which page to use as the archive when custom archive is enabled

### Setting Up Notice Types

1. Go to **Noticeboard → Types**
2. Add the types of notices you'll be publishing (e.g., "Meeting Summons", "Agenda", "Minutes", "Decision")
3. Configure display settings for each type if needed

### Setting Up Notice Groups

1. Go to **Noticeboard → Groups**
2. Add groups representing the entities that publish notices (e.g., "Municipal Board", "Building Committee")

### Using the Module

1. Edit a page and add the **Noticeboard** module using Modularity
2. Configure the module settings:
   - Choose display style
   - Set number of notices to display
   - Select specific notice types to show (optional)
   - Enable grouping by notice type
   - Enable archive button to link to full noticeboard

## Usage

### Creating a Notice

1. Go to **Noticeboard → Add New**
2. Enter the notice title
3. Add content or attach a PDF document
4. Select the appropriate notice type
5. Assign to the relevant group
6. Set publication and archive dates if applicable
7. Publish the notice

### Displaying Notices

Add the Noticeboard module to any page through Modularity to display notices. For a full archive view, either:

- Use the built-in archive at `/notice/` (or your configured slug)
- Create a page with the Noticeboard module in archive mode

## Hooks and Filters

The plugin provides several filters for customization:

- `Modularity/Module/Noticeboard/GroupIcon` – Customize the group icon
- `Modularity/Module/Noticeboard/GroupTitleVariant` – Customize group title heading level
- `Modularity/Module/Noticeboard/NoticeTitleVariant` – Customize notice title heading level
- `Modularity/Module/Noticeboard/TitleVariant` – Customize module title heading level
- `Modularity/Module/Noticeboard/ArchiveLabel` – Customize archive button text
- `Modularity/Module/Noticeboard/ArchiveIcon` – Customize archive button icon
- `Modularity/Module/Noticeboard/ArchiveButtonStyle` – Customize archive button styling

## License

MIT License - see [LICENSE](LICENSE) for details.

## Author

Developed by [Consid Borås AB](https://github.com/considbrs-webdev)
