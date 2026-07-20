/**
 * Normaliza una lista de valores textuales.
 *
 * @param {unknown} values Valores originales.
 * @return {string[]} Valores únicos y no vacíos.
 */
export function normalizeStringList(values) {
	if (!Array.isArray(values)) {
		return []
	}

	return [...new Set(
		values
			.map(value => String(value || '').trim())
			.filter(Boolean),
	)]
}

/**
 * Normaliza la metadata de una entrada.
 * Mantiene compatibilidad con el antiguo campo singular "category".
 *
 * @param {object|null} metadata Metadata recibida del servidor.
 * @return {{title: string, categories: string[], tags: string[]}}
 */
export function normalizeEntryMetadata(metadata = {}) {
	const source = metadata && typeof metadata === 'object'
		? metadata
		: {}

	let categories = normalizeStringList(source.categories)

	if (
		categories.length === 0
		&& typeof source.category === 'string'
		&& source.category.trim()
	) {
		categories = [source.category.trim()]
	}

	return {
		title: typeof source.title === 'string'
			? source.title.trim()
			: '',
		categories,
		tags: normalizeStringList(source.tags),
	}
}

/**
 * Construye la metadata que se enviará al servidor.
 *
 * @param {object} options Datos actuales de la entrada.
 * @param {string} options.title Título.
 * @param {string[]} options.categories Categorías.
 * @param {object[]} options.systemTags Etiquetas de Nextcloud.
 * @return {{title: string, categories: string[], tags: string[]}}
 */
export function buildEntryMetadata({
	title,
	categories,
	systemTags,
}) {
	return {
		title: String(title || '').trim(),
		categories: normalizeStringList(categories),
		tags: Array.isArray(systemTags)
			? normalizeStringList(
				systemTags.map(tag => tag?.name),
			)
			: [],
	}
}
