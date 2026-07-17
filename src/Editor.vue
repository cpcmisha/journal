<template>
	<div id="diary-editor">
		<div id="entry-title">
			<span>{{ unsavedMarker }}{{ title }}</span>

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
			{{ linkNotice }}
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
		}
	},

	computed: {
		title() {
			const day = moment(this.date)
			return `${day.format('dddd')} - ${day.format('LL')}`
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
		async loadEntry(entryDate) {
			const sequence = ++this.loadSequence

			clearTimeout(this.saveTimeout)
			this.saveTimeout = null
			this.saveArmed = false
			this.unsavedChanges = false
			this.status = 'loading'
			this.linkNotice = ''
			clearTimeout(this.linkNoticeTimeout)
			this.linkNoticeTimeout = null

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

				// eslint-disable-next-line no-console
				console.log(
					'Journal wikilink listener registered',
					entryDate,
					element,
				)

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

				this.showJournalNotice(
					t(
						'journalnotes',
						'This linked note has not been created yet.',
					),
				)
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

		showJournalNotice(message) {
			clearTimeout(this.linkNoticeTimeout)

			this.linkNotice = String(message || '')

			this.linkNoticeTimeout = window.setTimeout(() => {
				this.linkNotice = ''
				this.linkNoticeTimeout = null
			}, 5000)
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
		min-height: 54px;
		padding: 14px 28px;
		font-size: 18px;
		font-weight: 700;
		border-bottom: 1px solid var(--color-border);
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
		height: calc(100% - 54px);
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
		inset: 54px 0 0;
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

</style>
