import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/**
 * Obtiene el catálogo global de etiquetas.
 *
 * @return {Promise<object[]>} Etiquetas disponibles.
 */
export async function getTagCatalog() {
	const response = await axios.get(
		generateUrl('apps/journalnotes/system-tags'),
	)

	return Array.isArray(response.data)
		? response.data
		: []
}

/**
 * Obtiene las etiquetas asignadas a una entrada.
 *
 * @param {string} date Fecha YYYY-MM-DD.
 * @return {Promise<object[]>} Etiquetas de la entrada.
 */
export async function getEntryTags(date) {
	const response = await axios.get(
		generateUrl(
			`apps/journalnotes/entry/${date}/system-tags`,
		),
	)

	return Array.isArray(response.data)
		? response.data
		: []
}

/**
 * Crea una etiqueta global.
 *
 * @param {string} name Nombre de la etiqueta.
 * @return {Promise<object|null>} Etiqueta creada.
 */
export async function createTag(name) {
	const normalizedName = String(name || '').trim()

	if (!normalizedName) {
		return null
	}

	const response = await axios.post(
		generateUrl('apps/journalnotes/system-tags'),
		{
			name: normalizedName,
		},
	)

	return response.data || null
}

/**
 * Guarda las etiquetas asignadas a una entrada.
 *
 * @param {string} date Fecha YYYY-MM-DD.
 * @param {Array<string|number>} tagIds IDs de etiquetas.
 * @return {Promise<object[]>} Etiquetas guardadas.
 */
export async function saveEntryTags(date, tagIds) {
	const normalizedIds = Array.isArray(tagIds)
		? tagIds.map(id => String(id))
		: []

	const response = await axios.put(
		generateUrl(
			`apps/journalnotes/entry/${date}/system-tags`,
		),
		{
			tagIds: normalizedIds,
		},
	)

	return Array.isArray(response.data)
		? response.data
		: []
}
