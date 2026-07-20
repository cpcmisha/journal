import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Busca entradas del diario.
 *
 * @param {string} query Texto que desea buscar el usuario.
 * @param {number} limit Máximo de resultados.
 * @return {Promise<object[]>} Resultados normalizados.
 */
export async function searchEntries(
	query,
	limit = 100,
) {
	const normalizedQuery = String(query || '').trim()

	if (!normalizedQuery) {
		return []
	}

	const response = await axios.get(
		generateUrl('apps/journalnotes/search'),
		{
			params: {
				q: normalizedQuery,
				limit,
			},
		},
	)

	return Array.isArray(response.data)
		? response.data
		: []
}
