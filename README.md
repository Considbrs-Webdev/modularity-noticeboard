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

## External Publications

From version 1.1.0, **Noticeboard → Integrations** provides a shared Sokigo Nova
adapter and a general HTTPS bearer-token API for creating, updating and withdrawing
notices. Tokens are scoped to an integration source, operations and taxonomy terms.
The Nova adapter preserves the existing publication route and credential constants,
and uses durable external identities to avoid duplicate publications.

See [API setup, request examples and migration instructions](docs/integrations.md)
and [isolated integration tests](tests/README.md).

## Requirements

- WordPress 5.5+
- [Modularity](https://github.com/helsingborg-stad/Modularity) plugin
- [Advanced Custom Fields PRO](https://www.advancedcustomfields.com/pro/)
- PHP 8.2+

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
- **Breadcrumb title** – Customize the title displayed in breadcrumbs
- **Archival action** – Choose what happens when a notice is archived:
  - **Unpublish** (default) – Sets the notice to draft status, preserving it for future reference
  - **Delete** – Permanently removes the notice from the database

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

## WP-CLI Commands

The plugin includes WP-CLI commands for managing notices from the command line.

### Archive Notices

Archive notices that have passed their archive date:

```bash
# Archive all notices with passed archive dates
wp noticeboard archive

# Preview what would be archived (dry run)
wp noticeboard archive --dry-run
```

### List Notices

List all notices with their archive status:

```bash
# List notices in table format
wp noticeboard list

# Output as JSON
wp noticeboard list --format=json

# Output as CSV
wp noticeboard list --format=csv
```

## Scheduled Tasks (Cron)

The plugin does **not** archive notices on its own, and scheduled publication
depends on WordPress cron. Configure both tasks below as system cron jobs on the
server, running as the web server user.

### Archival

`wp noticeboard archive` unpublishes or deletes notices whose archive date and time
have passed, according to the configured archival action. A notice stays public
until the next run after its archive time, so the run interval is the maximum
delay before a notice is archived. Choose it from how promptly notices must be
removed; archive times have minute resolution.

```bash
# Edit the web server user's crontab
crontab -e

# Archive expired notices every 5 minutes. Adjust */5 to your required interval.
*/5 * * * * cd /path/to/wordpress && flock -n /tmp/noticeboard-archive.lock wp noticeboard archive --quiet
```

Replace `/path/to/wordpress` with the WordPress installation path. `flock`
prevents a slow run from overlapping the next one.

On multisite, the command only runs for the site given by `--url`. Add one line per
site that uses the noticeboard, with its own lock file:

```bash
*/5 * * * * cd /path/to/wordpress && flock -n /tmp/noticeboard-archive-example.lock wp noticeboard archive --url=https://example.se/ --quiet
```

Test the command with `--dry-run` before enabling the job.

### Scheduled publication

Notices with a future publication date, including those delivered through the
integrations, are published by WordPress cron. If `DISABLE_WP_CRON` is set, which is
recommended for predictable timing, run due events from system cron. Skip this if
the server already runs WordPress cron for the site.

```bash
# Single site
*/5 * * * * cd /path/to/wordpress && wp cron event run --due-now --quiet

# Multisite: run due events for every site
*/5 * * * * cd /path/to/wordpress && wp site list --field=url | xargs -I{} wp cron event run --due-now --url={} --quiet
```

## License

MIT License - see [LICENSE](LICENSE) for details.

## Author

Developed by [Consid Borås AB](https://github.com/considbrs-webdev)
