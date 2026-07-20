export function getWikiLinkTitle(link) {
	if (!link) {
		return ''
	}

	return String(
		link.getAttribute('data-md-href')
		|| link.getAttribute('href')
		|| link.textContent
		|| '',
	).trim()
}

export function getWikiLinkClass(status) {
	switch (status) {
		case 'found':
			return 'journal-wikilink--found'

		case 'multiple':
			return 'journal-wikilink--multiple'

		default:
			return 'journal-wikilink--missing'
	}
}

export function cleanWikiLinkExcerpt(
	excerpt,
	maxLength = 180,
) {
	return String(excerpt || '')
		.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
		.replace(/\\([\[\]])/g, '$1')
		.replace(/\s+/g, ' ')
		.trim()
		.substring(0, maxLength)
}
