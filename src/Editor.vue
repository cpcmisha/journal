<template>
	<div id="diary-editor">
		<div id="entry-title">
			<div class="entry-date-heading">
				<strong>
					{{ unsavedMarker }}{{ dateLabel }}
				</strong>

				<span>{{ weekdayLabel }}</span>
			</div>

			<span v-if="status === 'saving'" class="save-status">
				Guardando…
			</span>

			<span v-else-if="status === 'error'" class="save-status error">
				{{ t('journalnotes', 'Could not save') }}
			</span>
		</div>

		<div
			v-if="linkNotice"
			class="link-notice"
			role="status">
			<span>{{ linkNotice }}</span>

			<div
				v-if="pendingLinkedTitle"
				class="link-notice__actions">
				<input
					v-model="linkedNoteDate"
					type="date"
					class="link-notice__date"
					:max="today"
					:aria-label="t('journalnotes', 'Date for the new linked note')">

				<button
					type="button"
					class="primary"
					:disabled="creatingLinkedNote"
					@click="createLinkedNote">
					{{
						creatingLinkedNote
							? t('journalnotes', 'Creating note…')
							: t('journalnotes', 'Create note')
					}}
				</button>

				<button
					type="button"
					:disabled="creatingLinkedNote"
					@click="dismissLinkNotice">
					{{ t('journalnotes', 'Cancel') }}
				</button>
			</div>
		</div>

		<div
			v-if="wikiAutocompleteOpen"
			class="wiki-autocomplete"
			role="listbox"
			:aria-label="t('journalnotes', 'Linked note suggestions')">
			<div class="wiki-autocomplete__header">
				<span>{{ t('journalnotes', 'Link to a note') }}</span>
				<small>[[{{ wikiAutocompleteQuery }}</small>
			</div>

			<button
				v-for="(suggestion, index) in wikiAutocompleteSuggestions"
				:key="`${suggestion.date}-${suggestion.title}`"
				type="button"
				class="wiki-autocomplete__item"
				:class="{
					'wiki-autocomplete__item--active':
						index === wikiAutocompleteIndex,
				}"
				role="option"
				:aria-selected="index === wikiAutocompleteIndex"
				@pointerdown.prevent="selectWikiSuggestion(suggestion)">
				<strong>{{ suggestion.title }}</strong>

				<small v-if="suggestion.date">
					{{ formatWikiSuggestionDate(suggestion.date) }}
				</small>

				<span v-if="suggestion.excerpt">
					{{ cleanWikiSuggestionExcerpt(suggestion.excerpt) }}
				</span>
			</button>

			<div
				v-if="wikiAutocompleteLoading"
				class="wiki-autocomplete__empty">
				{{ t('journalnotes', 'Searching notes…') }}
			</div>

			<button
				v-else-if="
					wikiAutocompleteQuery
						&& wikiAutocompleteSuggestions.length === 0
				"
				type="button"
				class="wiki-autocomplete__create"
				@pointerdown.prevent="
					showCreateLinkedNote(wikiAutocompleteQuery)
				">
				{{
					t(
						'journalnotes',
						'Create note: {title}',
						{ title: wikiAutocompleteQuery },
					)
				}}
			</button>
		</div>

		<!-- La clave obliga a Vue a crear un contenedor nuevo por fecha. -->
		<div
			v-if="textAvailable"
			:key="editorKey"
			ref="textEditor"
			class="text-editor" />

		<div v-else class="text-unavailable">
			<h2>Nextcloud Text no está disponible</h2>
			<p>Activa la aplicación Text para usar el editor.</p>
		</div>

		<div v-if="isLoading" id="overlay">
			<div class="loading-indicator">
				Cargando…
			</div>
		</div>
	</div>
</template>

<script>
import { markRaw } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

export default {
	name: 'Editor',

	props: {
		date: {
			type: String,
			required: true,
		},
	},

	emits: ['entry-edit'],

	data() {
		return {
			status: 'loading',
			content: '',
			editor: null,
			saveTimeout: null,
			unsavedChanges: false,
			isSettingContent: false,
			saveArmed: false,
			loadSequence: 0,
			editorKey: 0,
			editorClickHandler: null,
			linkNotice: '',
			linkNoticeTimeout: null,
			pendingLinkedTitle: '',
			linkedNoteDate: moment().format('YYYY-MM-DD'),
			creatingLinkedNote: false,
			wikiLinkStateRequestId: 0,
			wikiAutocompleteOpen: false,
			wikiAutocompleteQuery: '',
			wikiAutocompleteSuggestions: [],
			wikiAutocompleteIndex: 0,
			wikiAutocompleteLoading: false,
			wikiAutocompleteTimeout: null,
			wikiAutocompleteRequestId: 0,
			editorKeydownHandler: null,
		}
	},

	computed: {
		today() {
			return moment().format('YYYY-MM-DD')
		},

		dateLabel() {
			return moment(this.date).format('LL')
		},

		weekdayLabel() {
			return moment(this.date).format('dddd')
		},

		unsavedMarker() {
			return this.unsavedChanges ? '* ' : ''
		},

		isLoading() {
			return this.status === 'loading'
		},

		textAvailable() {
			return Boolean(
				window.OCA
				&& window.OCA.Text
				&& typeof window.OCA.Text.createEditor === 'function',
			)
		},
	},

	watch: {
		'$route.params.date': {
			immediate: true,
			handler(newDate) {
				this.loadEntry(newDate)
			},
		},
	},

	beforeUnmount() {
		clearTimeout(this.saveTimeout)
		clearTimeout(this.linkNoticeTimeout)
		this.destroyEditor()
	},

	methods: {
		/**
		 * Aplica una plantilla a la entrada actual y la guarda.
		 * Journal.vue puede invocarlo mediante this.$refs.editor.
		 */
		async loadEntry(entryDate) {
			const sequence = ++this.loadSequence

			clearTimeout(this.saveTimeout)
			this.saveTimeout = null
			this.saveArmed = false
			this.unsavedChanges = false
			this.status = 'loading'
			this.dismissLinkNotice()
			this.linkedNoteDate = this.today

			await this.destroyEditor()

			try {
				const response = await axios.get(
					generateUrl(`apps/journalnotes/entry/${entryDate}`),
				)

				if (sequence !== this.loadSequence) {
					return
				}

				this.content = response.data.entryContent || ''

				/*
				 * Fuerza un elemento DOM nuevo para cada fecha.
				 * Evita que Text reutilice el documento anterior.
				 */
				this.editorKey++
				await this.$nextTick()

				await this.createTextEditor(
					sequence,
					entryDate,
					this.content,
				)
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('No se pudo cargar la entrada', error)
				this.status = 'error'
			}
		},

		async createTextEditor(sequence, entryDate, initialContent) {
			if (!this.textAvailable) {
				this.status = 'error'
				return
			}

			await this.$nextTick()

			const element = this.$refs.textEditor

			if (!element || sequence !== this.loadSequence) {
				return
			}

			this.isSettingContent = true

			try {
				const editor = await window.OCA.Text.createEditor({
					el: element,
					content: initialContent || '',
					readOnly: false,

					onUpdate: (update) => {

						if (
							this.isSettingContent
							|| !this.saveArmed
							|| sequence !== this.loadSequence
						) {
							return
						}

						const markdown = update?.markdown

						if (typeof markdown !== 'string') {
							// eslint-disable-next-line no-console
							console.error(
								'Nextcloud Text no devolvió Markdown válido',
								update,
							)
							return
						}

						this.handleUpdate(entryDate, markdown)
					},
				})

				if (sequence !== this.loadSequence) {
					editor?.destroy?.()
					return
				}

				/*
				 * Nextcloud Text utiliza campos privados de JavaScript.
				 * Vue no debe convertir esta instancia en un Proxy reactivo.
				 */
				this.editor = markRaw(editor)

				this.editorClickHandler = event => {
					this.handleEditorClick(event)
				}

				element.addEventListener(
					'pointerdown',
					this.editorClickHandler,
					true,
				)

				const finishLoading = () => {
					if (sequence !== this.loadSequence) {
						return
					}

					this.isSettingContent = false
					this.status = 'loaded'

					window.setTimeout(() => {
						if (sequence === this.loadSequence) {
							this.refreshWikiLinkStates()
						}
					}, 100)

					/*
					 * Text emite transacciones internas durante la carga.
					 * Esperamos un momento antes de considerar cambios del usuario.
					 */
					window.setTimeout(() => {
						if (sequence === this.loadSequence) {
							this.saveArmed = true
						}
					}, 500)
				}

				if (typeof this.editor.onLoaded === 'function') {
					this.editor.onLoaded(finishLoading)
				} else {
					this.$nextTick(finishLoading)
				}
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not start Nextcloud Text'),
					error,
				)
				this.isSettingContent = false
				this.status = 'error'
			}
		},

		async refreshWikiLinkStates() {
			const container = this.$refs.textEditor

			if (!container) {
				return
			}

			const links = Array.from(
				container.querySelectorAll('a[iswikilink]'),
			)

			if (links.length === 0) {
				return
			}

			const requestId = ++this.wikiLinkStateRequestId
			const linksByTitle = new Map()

			for (const link of links) {
				const title = String(
					link.getAttribute('data-md-href')
						|| link.getAttribute('href')
						|| link.textContent
						|| '',
				).trim()

				if (!title) {
					continue
				}

				link.classList.remove(
					'journal-wikilink--found',
					'journal-wikilink--missing',
					'journal-wikilink--multiple',
				)

				link.classList.add('journal-wikilink--checking')

				if (!linksByTitle.has(title)) {
					linksByTitle.set(title, [])
				}

				linksByTitle.get(title).push(link)
			}

			await Promise.all(
				Array.from(linksByTitle.entries()).map(
					async ([title, titleLinks]) => {
						let status = 'not_found'
						let matches = []

						try {
							const response = await axios.get(
								generateUrl(
									'apps/journalnotes/resolve-note',
								),
								{
									params: { title },
								},
							)

							const data = response.data || {}

							status = String(
								data.status || 'not_found',
							)

							matches = Array.isArray(data.matches)
								? data.matches
								: []
						} catch (error) {
							// eslint-disable-next-line no-console
							console.error(
								t(
									'journalnotes',
									'Could not resolve the linked note',
								),
								error,
							)
						}

						if (
							requestId !== this.wikiLinkStateRequestId
							|| !this.$refs.textEditor
						) {
							return
						}

						const className = status === 'found'
							? 'journal-wikilink--found'
							: status === 'multiple'
								? 'journal-wikilink--multiple'
								: 'journal-wikilink--missing'

						let tooltip = ''

						if (
							status === 'found'
							&& matches.length === 1
						) {
							const match = matches[0] || {}
							const matchTitle = String(
								match.title || title,
							).trim()

							const formattedDate = match.date
								? moment(match.date).format('LL')
								: ''

							/*
							 * Convertimos enlaces Markdown y secuencias
							 * escapadas en texto legible para el tooltip.
							 */
							const excerpt = String(
								match.excerpt || '',
							)
								.replace(
									/\[([^\]]+)\]\([^)]+\)/g,
									'$1',
								)
								.replace(/\\([\[\]])/g, '$1')
								.replace(/\s+/g, ' ')
								.trim()
								.substring(0, 180)

							tooltip = [
								matchTitle,
								formattedDate,
								excerpt,
							]
								.filter(Boolean)
								.join('\n')
						} else if (status === 'multiple') {
							tooltip = [
								`${matches.length}`,
								t(
									'journalnotes',
									'Several notes use this title',
								),
								title,
							]
								.filter(Boolean)
								.join(' · ')
						} else {
							tooltip = [
								t(
									'journalnotes',
									'Create note',
								),
								title,
							]
								.filter(Boolean)
								.join(': ')
						}

						for (const link of titleLinks) {
							link.classList.remove(
								'journal-wikilink--checking',
							)

							link.classList.add(className)
							link.dataset.journalLinkStatus = status
							link.title = tooltip
						}
					},
				),
			)
		},

		async handleEditorClick(event) {
			const link = event.target?.closest?.('a')

			if (
				event.button !== undefined
				&& event.button !== 0
			) {
				return
			}

			if (!link || !this.$refs.textEditor?.contains(link)) {
				return
			}

			const isWikiLink = link.getAttribute('iswikilink') === 'true'
				|| link.hasAttribute('iswikilink')

			if (!isWikiLink) {
				return
			}

			/*
			 * Nextcloud Text guarda el título original del wikilink
			 * en data-md-href. El atributo href puede ser relativo.
			 */
			const linkedTitle = String(
				link.getAttribute('data-md-href')
					|| link.getAttribute('href')
					|| link.textContent
					|| '',
			).trim()

			if (!linkedTitle) {
				return
			}

			/*
			 * Debemos detener el clic antes de que Nextcloud Text abra
			 * su ventana flotante de enlaces.
			 */
			event.preventDefault()
			event.stopPropagation()
			event.stopImmediatePropagation?.()

			try {
				const response = await axios.get(
					generateUrl('apps/journalnotes/resolve-note'),
					{
						params: {
							title: linkedTitle,
						},
					},
				)

				const data = response.data || {}
				const matches = Array.isArray(data.matches)
					? data.matches
					: []

				if (
					data.status === 'found'
					&& matches.length === 1
					&& matches[0]?.date
				) {
					const targetDate = matches[0].date

					/*
					 * Una autorreferencia no necesita recargar la entrada.
					 */
					if (targetDate === this.date) {
						this.showJournalNotice(
							t(
								'journalnotes',
								'You are already viewing this note.',
							),
						)
						return
					}

					await this.$router.push({
						name: 'date',
						params: {
							date: targetDate,
						},
					})
					return
				}

				if (data.status === 'multiple') {
					this.showJournalNotice(
						t(
							'journalnotes',
							'Several notes use this title. Select a date from Relations.',
						),
					)
					return
				}

				this.showCreateLinkedNote(linkedTitle)
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error(
					t(
						'journalnotes',
						'Could not resolve the linked note',
					),
					error,
				)

				this.showJournalNotice(
					t(
						'journalnotes',
						'Could not open the linked note.',
					),
				)
			}
		},

		showCreateLinkedNote(title) {
			const linkedTitle = String(title || '').trim()

			if (!linkedTitle) {
				return
			}

			this.pendingLinkedTitle = linkedTitle
			this.linkedNoteDate = this.today

			this.showJournalNotice(
				t(
					'journalnotes',
					'This linked note has not been created yet.',
				),
				false,
			)
		},

		showJournalNotice(message, autoHide = true) {
			clearTimeout(this.linkNoticeTimeout)

			this.linkNotice = String(message || '')

			if (!autoHide) {
				this.linkNoticeTimeout = null
				return
			}

			this.linkNoticeTimeout = window.setTimeout(() => {
				this.dismissLinkNotice()
			}, 5000)
		},

		dismissLinkNotice() {
			clearTimeout(this.linkNoticeTimeout)

			this.linkNotice = ''
			this.linkNoticeTimeout = null
			this.pendingLinkedTitle = ''
			this.creatingLinkedNote = false
		},

		async createLinkedNote() {
			const title = this.pendingLinkedTitle.trim()
			const date = this.linkedNoteDate

			if (!title || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
				this.showJournalNotice(
					t('journalnotes', 'Select a valid date.'),
					false,
				)
				return
			}

			this.creatingLinkedNote = true

			try {
				/*
				 * Journal solo admite una entrada por fecha.
				 * Comprobamos que no exista contenido para evitar sobrescribirla.
				 */
				const existingResponse = await axios.get(
					generateUrl(`apps/journalnotes/entry/${date}`),
				)

				const existing = existingResponse.data || {}
				const existingContent = String(
					existing.entryContent || '',
				).trim()

				if (
					existing.isEmpty !== true
					&& existingContent !== ''
				) {
					this.creatingLinkedNote = false
					this.showJournalNotice(
						t(
							'journalnotes',
							'This date already contains an entry. Choose another date.',
						),
						false,
					)
					return
				}

				const initialContent = `# ${title}\n\n`
				const metadata = {
					title,
					categories: [],
					tags: [],
				}

				await axios.put(
					generateUrl(`apps/journalnotes/entry/${date}`),
					{
						content: initialContent,
						metadataJson: JSON.stringify(metadata),
					},
				)

				this.dismissLinkNotice()

				await this.$router.push({
					name: 'date',
					params: { date },
				})
			} catch (error) {
				this.creatingLinkedNote = false

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not create the linked note'),
					error,
				)

				this.showJournalNotice(
					t(
						'journalnotes',
						'Could not create the linked note.',
					),
					false,
				)
			}
		},

		handleUpdate(entryDate, markdown) {
			this.content = markdown
			this.unsavedChanges = true
			this.status = 'writing'

			clearTimeout(this.saveTimeout)

			this.saveTimeout = setTimeout(() => {
				this.saveEntry(entryDate, markdown)
			}, 700)
		},

		async saveEntry(entryDate, markdown) {
			if (typeof markdown !== 'string') {
				this.status = 'error'
				return
			}

			this.status = 'saving'

			try {
				const response = await axios.put(
					generateUrl(`apps/journalnotes/entry/${entryDate}`),
					{ content: markdown },
				)

				/*
				 * Si el usuario cambió de fecha mientras guardábamos,
				 * no actualizamos visualmente la nueva entrada.
				 */
				if (entryDate !== this.$route.params.date) {
					return
				}

				this.content = markdown
				this.unsavedChanges = false
				this.status = 'loaded'

				this.$emit(
					'entry-edit',
					entryDate,
					response.data.isEmpty
						? false
						: response.data.entryContent,
				)
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not save the entry'),
					{
						entryDate,
						markdown,
						error,
					},
				)
				this.status = 'error'
			}
		},

		async destroyEditor() {
			this.saveArmed = false
			this.wikiLinkStateRequestId++
			clearTimeout(this.saveTimeout)
			this.saveTimeout = null

			const element = this.$refs.textEditor

			if (element && this.editorClickHandler) {
				element.removeEventListener(
					'pointerdown',
					this.editorClickHandler,
					true,
				)
			}

			this.editorClickHandler = null

			const oldEditor = this.editor
			this.editor = null

			if (
				oldEditor
				&& typeof oldEditor.destroy === 'function'
			) {
				try {
					await oldEditor.destroy()
				} catch (error) {
					// eslint-disable-next-line no-console
					console.warn(
						'No se pudo destruir completamente el editor anterior',
						error,
					)
				}
			}

			if (element) {
				element.replaceChildren()
			}
		},
	},
}
</script>

<style lang="scss">
#diary-editor {
	position: relative;
	width: 100%;
	min-width: 0;
	height: 100%;
	box-sizing: border-box;

	#entry-title {
		display: flex;
		align-items: center;
		justify-content: space-between;
		min-height: 62px;
		padding: 10px 28px;
		border-bottom: 1px solid var(--color-border);
		box-sizing: border-box;
	}

	.entry-date-heading {
		display: flex;
		min-width: 0;
		flex-direction: column;
		gap: 2px;

		strong {
			overflow: hidden;
			font-size: 18px;
			font-weight: 700;
			line-height: 1.25;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		span {
			color: var(--color-text-maxcontrast);
			font-size: 13px;
			font-weight: 500;
			line-height: 1.2;
			text-transform: capitalize;
		}
	}

	.save-status {
		font-size: 13px;
		font-weight: 400;
		color: var(--color-text-maxcontrast);

		&.error {
			color: var(--color-error);
		}
	}

	.text-editor {
		width: 100%;
		min-width: 0;
		height: calc(100% - 62px);
		min-height: 65vh;
		box-sizing: border-box;
	}

	.text-unavailable {
		margin: 40px;
		padding: 30px;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
		background: var(--color-background-hover);
	}

	#overlay {
		display: flex;
		position: absolute;
		inset: 62px 0 0;
		z-index: 100;
		align-items: center;
		justify-content: center;
		background: rgb(0 0 0 / 25%);
		pointer-events: none;
	}

	.loading-indicator {
		padding: 12px 18px;
		border-radius: var(--border-radius-large);
		background: var(--color-main-background);
		box-shadow: 0 4px 16px rgb(0 0 0 / 25%);
	}
}

#diary-editor .text-editor > div {
	width: 100%;
	min-width: 0;
	height: 100%;
}

/* Journal usa todo el ancho disponible del panel central. */
#diary-editor .text-editor,
#diary-editor .text-editor > div,
#diary-editor .text-editor .editor,
#diary-editor .text-editor .editor-wrapper,
#diary-editor .text-editor .editor__content,
#diary-editor .text-editor .content-wrapper,
#diary-editor .text-editor .ProseMirror {
	width: 100% !important;
	max-width: none !important;
	min-width: 0 !important;
	box-sizing: border-box !important;
}

#diary-editor .text-editor .ProseMirror {
	padding-right: 32px !important;
	padding-left: 32px !important;
}


/* Nextcloud Text debe ocupar todo el espacio restante de Journal. */
#diary-editor,
#diary-editor .text-editor,
#diary-editor .text-editor > div {
	width: 100% !important;
	max-width: none !important;
	min-width: 0 !important;
}

#diary-editor .text-editor :is(
	.editor,
	.editor-wrapper,
	.editor__content,
	.content-wrapper,
	.rich-workspace,
	.ProseMirror-menubar-wrapper,
	.ProseMirror
) {
	width: 100% !important;
	max-width: none !important;
	min-width: 0 !important;
	box-sizing: border-box !important;
}

#diary-editor .text-editor .ProseMirror {
	padding-right: 40px !important;
	padding-left: 40px !important;
}

.link-notice {
	margin: 8px 16px;
	padding: 10px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
	color: var(--color-main-text);
	font-size: 14px;
}

.link-notice__actions {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 10px;
}

.link-notice__date {
	min-height: 36px;
	padding: 4px 10px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.link-notice__actions button {
	min-height: 36px;
}

.text-editor a[iswikilink] {
	text-underline-offset: 3px;
	transition:
		color 120ms ease,
		text-decoration-color 120ms ease;
}

.text-editor a.journal-wikilink--checking {
	opacity: 0.75;
}

.text-editor a.journal-wikilink--found {
	color: var(--color-primary-element);
	text-decoration-style: solid;
	cursor: pointer;
}

.text-editor a.journal-wikilink--missing {
	color: var(--color-text-maxcontrast);
	text-decoration-line: underline;
	text-decoration-style: dashed;
	text-decoration-color: var(--color-text-maxcontrast);
	cursor: pointer;
}

.text-editor a.journal-wikilink--multiple {
	color: var(--color-warning-text);
	text-decoration-line: underline;
	text-decoration-style: double;
	text-decoration-color: var(--color-warning);
	cursor: pointer;
}

.wiki-autocomplete {
	position: absolute;
	z-index: 1200;
	top: 112px;
	left: 48px;
	width: min(420px, calc(100% - 96px));
	max-height: 360px;
	overflow-y: auto;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
	box-shadow: 0 8px 28px rgb(0 0 0 / 30%);
}

.wiki-autocomplete__header {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	padding: 6px 10px 10px;
	color: var(--color-text-maxcontrast);
}

.wiki-autocomplete__item,
.wiki-autocomplete__create {
	display: flex;
	width: 100%;
	flex-direction: column;
	align-items: flex-start;
	gap: 2px;
	min-height: 54px;
	padding: 8px 10px;
	border: 0;
	border-radius: var(--border-radius);
	background: transparent;
	color: var(--color-main-text);
	text-align: left;
}

.wiki-autocomplete__item:hover,
.wiki-autocomplete__item--active,
.wiki-autocomplete__create:hover {
	background: var(--color-background-hover);
}

.wiki-autocomplete__item small {
	color: var(--color-text-maxcontrast);
}

.wiki-autocomplete__item span {
	display: block;
	max-width: 100%;
	overflow: hidden;
	color: var(--color-text-maxcontrast);
	text-overflow: ellipsis;
	white-space: nowrap;
}

.wiki-autocomplete__empty {
	padding: 14px 10px;
	color: var(--color-text-maxcontrast);
}


/* Wikilinks: resaltado sencillo al pasar el cursor. */
#diary-editor .text-editor a[iswikilink] {
	padding: 1px 4px;
	border-radius: var(--border-radius);
	text-decoration: none;
	transition: background-color 120ms ease;
}

#diary-editor .text-editor a[iswikilink]:hover,
#diary-editor .text-editor a[iswikilink]:focus-visible {
	background: var(--color-primary-element-light);
	text-decoration: none;
}

#diary-editor .text-editor a[iswikilink]:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 1px;
}

</style>
