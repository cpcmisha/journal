/**
 * Obtiene el título original de un wikilink de Nextcloud Text.
 *
 * @param {HTMLElement|null} link Elemento del enlace.
 * @return {string} Título normalizado.
 */
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

/**
 * Obtiene la clase visual según el estado del wikilink.
 *
 * @param {string} status Estado devuelto por el servidor.
 * @return {string} Clase CSS.
 */
export function getWikiLinkClass(status) {
	if (status === 'found') {
		return 'journal-wikilink--found'
	}

	if (status === 'multiple') {
		return 'journal-wikilink--multiple'
	}

	return 'journal-wikilink--missing'
}

/**
 * Convierte un extracto Markdown en texto breve y legible.
 *
 * @param {string} excerpt Texto original.
 * @param {number} maxLength Longitud máxima.
 * @return {string} Extracto limpio.
 */
export function cleanWikiLinkExcerpt(excerpt, maxLength = 180) {
	return String(excerpt || '')
		.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
		.replace(/\\([\[\]])/g, '$1')
		.replace(/\s+/g, ' ')
		.trim()
		.substring(0, maxLength)
}
