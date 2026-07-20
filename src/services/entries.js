import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Obtiene una entrada por fecha.
 *
 * @param {string} date Fecha YYYY-MM-DD.
 * @return {Promise<object>} Datos normalizados de la entrada.
 */
export async function getEntry(date) {
	const response = await axios.get(
		generateUrl(`apps/journalnotes/entry/${date}`),
	)

	return response.data || {}
}

/**
 * Guarda contenido y, opcionalmente, metadata.
 *
 * @param {string} date Fecha YYYY-MM-DD.
 * @param {string} content Contenido Markdown.
 * @param {object|null} metadata Metadata opcional.
 * @return {Promise<object>} Respuesta normalizada.
 */
export async function saveEntry(
	date,
	content,
	metadata = null,
) {
	const payload = {
		content: String(content ?? ''),
	}

	if (metadata !== null) {
		payload.metadataJson = JSON.stringify(metadata)
	}

	const response = await axios.put(
		generateUrl(`apps/journalnotes/entry/${date}`),
		payload,
	)

	return response.data || {}
}

/**
 * Indica si una fecha ya contiene contenido.
 *
 * @param {string} date Fecha YYYY-MM-DD.
 * @return {Promise<boolean>} true si ya existe una entrada con contenido.
 */
export async function entryHasContent(date) {
	const entry = await getEntry(date)

	const content = String(
		entry.entryContent || '',
	).trim()

	return entry.isEmpty !== true && content !== ''
}
