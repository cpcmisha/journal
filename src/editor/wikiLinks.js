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

/**
 * Agrupa los wikilinks renderizados según su título.
 *
 * @param {HTMLElement[]} links Enlaces encontrados en el editor.
 * @return {Map<string, HTMLElement[]>} Enlaces agrupados por título.
 */
export function groupWikiLinksByTitle(links) {
	const linksByTitle = new Map()

	for (const link of links) {
		const title = getWikiLinkTitle(link)

		if (!title) {
			continue
		}

		if (!linksByTitle.has(title)) {
			linksByTitle.set(title, [])
		}

		linksByTitle.get(title).push(link)
	}

	return linksByTitle
}
