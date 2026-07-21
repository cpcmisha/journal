import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Obtiene las relaciones entrantes y salientes de una entrada.
 *
 * @param {string} date Fecha YYYY-MM-DD.
 * @param {number} limit Número máximo de relaciones.
 * @return {Promise<object>} Relaciones normalizadas.
 */
export async function getRelations(
	date,
	limit = 100,
) {
	const response = await axios.get(
		generateUrl('apps/journalnotes/relations'),
		{
			params: {
				date,
				limit,
			},
		},
	)

	const data = response.data || {}

	return {
		outgoing: Array.isArray(data.outgoing)
			? data.outgoing
			: [],
		incoming: Array.isArray(data.incoming)
			? data.incoming
			: [],
	}
}
