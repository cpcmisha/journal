# Journal 0.2.0

First public release of Journal.

Journal is a private Markdown journal and lightweight personal knowledge
workspace for Nextcloud.

## Highlights

- Daily journal entries organized by date
- Native Markdown storage
- YAML Front Matter metadata
- Rich-text editing with Nextcloud Text
- Multiple categories
- Native Nextcloud System Tags
- Full-text search
- Wikilinks
- Relations between notes
- Relations explorer
- Note inspector
- Markdown export
- Individual and combined PDF export
- Dashboard widget
- Responsive layout for desktop, tablet and mobile
- English, Spanish and German translations
- OCC migration commands

## Stability

PHP dependencies are isolated to prevent conflicts with Nextcloud and other
applications.

The release includes fixes for conflicts involving:

- Dompdf
- TCPDF
- CommonMark
- FontLib
- Masterminds HTML5
- PSR Log
- libmergepdf

## Compatibility

- Nextcloud 34
- PHP 8.2–8.4

## Storage

Journal entries are stored as regular Markdown files inside the user's
Nextcloud storage.

The files remain accessible even when Journal is disabled or removed.
