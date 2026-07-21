import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Busca entradas cuyo título coincide con el wikilink.
 *
 * @param {string} title Título de la nota enlazada.
 * @return {Promise<object>} Estado y coincidencias normalizados.
 */
export async function resolveLinkedNote(title) {
	const normalizedTitle = String(title || '').trim()

	if (!normalizedTitle) {
		return {
			title: '',
			status: 'not_found',
			matches: [],
		}
	}

	const response = await axios.get(
		generateUrl('apps/journalnotes/resolve-note'),
		{
			params: {
				title: normalizedTitle,
			},
		},
	)

	const data = response.data || {}

	return {
		...data,
		title: String(data.title || normalizedTitle),
		status: String(data.status || 'not_found'),
		matches: Array.isArray(data.matches)
			? data.matches
			: [],
	}
}
