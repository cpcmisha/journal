# Changelog

All notable changes to Journal will be documented in this file.

## [0.2.0] - 2026-07-20

### Added

- Rich-text editor powered by Nextcloud Text.
- Native Markdown storage.
- YAML Front Matter metadata.
- Multiple categories.
- Nextcloud System Tags integration.
- Note inspector sidebar.
- Relations between notes.
- Wikilinks and relations explorer.
- Full-text search across content, dates, categories, tags and wikilinks.
- Date navigation.
- Markdown export.
- Individual and combined PDF export.
- Emoji support.
- English, Spanish and German translations.
- OCC migration commands.

### Fixed

- Isolated PHP dependencies to prevent conflicts with Nextcloud.
- Fixed the WebDAV endpoint check.
- Fixed conflicts involving Dompdf, TCPDF, CommonMark, FontLib, Masterminds HTML5 and PSR Log.
- Fixed external URLs being incorrectly interpreted as wikilinks.
- Improved PDF generation and PDF merge stability.

### Compatibility

- Nextcloud 34.
- PHP 8.2–8.4.
