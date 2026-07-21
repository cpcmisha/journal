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

/**
 * Construye el tooltip de un wikilink según su estado.
 *
 * @param {object} options Opciones del tooltip.
 * @param {string} options.status Estado del enlace.
 * @param {object[]} options.matches Coincidencias encontradas.
 * @param {string} options.title Título original del enlace.
 * @param {Function} options.formatDate Función para formatear fechas.
 * @param {object} options.labels Textos traducidos.
 * @return {string} Tooltip listo para asignar al enlace.
 */
export function buildWikiLinkTooltip({
	status,
	matches,
	title,
	formatDate,
	labels,
}) {
	if (
		status === 'found'
		&& matches.length === 1
	) {
		const match = matches[0] || {}

		const matchTitle = String(
			match.title || title,
		).trim()

		const formattedDate = match.date
			? formatDate(match.date)
			: ''

		const excerpt = cleanWikiLinkExcerpt(
			match.excerpt,
		)

		return [
			matchTitle,
			formattedDate,
			excerpt,
		]
			.filter(Boolean)
			.join('\n')
	}

	if (status === 'multiple') {
		return [
			`${matches.length}`,
			labels.multiple,
			title,
		]
			.filter(Boolean)
			.join(' · ')
	}

	return [
		labels.create,
		title,
	]
		.filter(Boolean)
		.join(': ')
}

/**
 * Aplica el estado visual y el tooltip a un wikilink.
 *
 * @param {HTMLElement|null} link Enlace renderizado.
 * @param {object} options Opciones visuales.
 * @param {string} options.status Estado del enlace.
 * @param {string} options.className Clase CSS.
 * @param {string} options.tooltip Texto del tooltip.
 */
export function applyWikiLinkState(
	link,
	{
		status,
		className,
		tooltip,
	},
) {
	if (!link) {
		return
	}

	link.classList.remove(
		'journal-wikilink--checking',
		'journal-wikilink--found',
		'journal-wikilink--missing',
		'journal-wikilink--multiple',
	)

	link.classList.add(className)
	link.dataset.journalLinkStatus = status
	link.title = tooltip
}
