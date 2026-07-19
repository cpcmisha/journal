<template>
	<NcContent id="journal-content" app-name="journalnotes">
		<!-- Primera columna: fechas -->
		<NcAppNavigation class="dates-navigation">
			<div class="navigation-wrapper">
				<NcButton
					class="icon icon-view-previous"
					aria-label="Día anterior"
					@click="goPrevDay" />

				<input
					ref="nativeDatePicker"
					class="native-date-picker"
					type="date"
					:value="date"
					:max="today"
					@change="onNativeDateChange">

				<NcButton
					class="open-calendar"
					:aria-label="t('journalnotes', 'Open calendar: {date}', { date: formattedDate })"
					@click="openCalendar">
					{{ formattedDate }}
				</NcButton>

				<NcButton
					v-if="showNextDayButton"
					class="icon icon-view-next"
					aria-label="Día siguiente"
					@click="goNextDay" />
			</div>

			<template #list>
				<ul>
					<NcListItem
						v-for="entry in filteredEntries"
						:key="entry.date"
						:name="entry.title || formatDate(entry.date)"
						:bold="false"
						:compact="true"
						counter-type="highlighted"
						@click="!isCurrentDate(entry.date)
							? onDateChange(entry.date)
							: null">
						<template #icon>
							<NcAppNavigationIconBullet
								v-if="isCurrentDate(entry.date)"
								color="0082c9" />
							<NcAppNavigationIconBullet
								v-else
								color="FFFFFF" />
						</template>

						<template #subname>
							{{ entry.excerpt }}
						</template>
					</NcListItem>
				</ul>
			</template>

			<template #footer>
				<NcAppNavigationItem
					class="export"
					:title="t('journalnotes', 'Export')"
					icon="icon-download">
					<template #actions>
						<NcActionLink :href="pdfDownloadLink">
							<template #icon>
								<FilePdfBox :size="20" />
								{{ t('journalnotes', 'as PDF') }}
							</template>
						</NcActionLink>

						<NcActionLink :href="markdownDownloadLink">
							<template #icon>
								<Markdown :size="20" />
								{{ t('journalnotes', 'as Markdown') }}
							</template>
						</NcActionLink>
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent class="journal-app-content">
			<div
				class="journal-workspace"
				:class="{ 'journal-workspace--welcome': !hasContent }">
				<div class="responsive-panel-toolbar">
					<NcButton
						type="tertiary"
						class="responsive-panel-button"
						:aria-expanded="explorePanelOpen"
						:aria-label="t('journalnotes', 'Open Explore panel')"
						@click="openExplorePanel">
						<span
							class="icon-menu"
							aria-hidden="true" />
						{{ t('journalnotes', 'Explore') }}
					</NcButton>

					<NcButton
						v-if="hasContent"
						type="tertiary"
						class="responsive-panel-button"
						:aria-expanded="inspectorPanelOpen"
						:aria-label="t('journalnotes', 'Open note information')"
						@click="openInspectorPanel">
						<span
							class="icon-info"
							aria-hidden="true" />
						{{ t('journalnotes', 'Note') }}
					</NcButton>
				</div>

				<button
					v-if="explorePanelOpen || inspectorPanelOpen"
					type="button"
					class="responsive-panel-backdrop"
					:aria-label="t('journalnotes', 'Close side panel')"
					@click="closeResponsivePanels" />

				<!-- Segunda columna: organización -->
				<aside
					class="organizer-panel"
					:class="{
						'organizer-panel--open': explorePanelOpen,
					}">
					<div class="organizer-header">
						<h2>{{ t('journalnotes', 'Explore') }}</h2>

						<NcButton
							type="tertiary"
							class="responsive-panel-close"
							:aria-label="t('journalnotes', 'Close Explore panel')"
							@click="closeResponsivePanels">
							<span
								class="icon-close"
								aria-hidden="true" />
						</NcButton>
					</div>

					<section class="global-filters">
						<label for="journal-search">
							{{ t('journalnotes', 'Search notes') }}
						</label>

						<input
							id="journal-search"
							v-model.trim="searchQuery"
							type="search"
							:placeholder="t('journalnotes', 'Search dates and excerpts')">

						<p
							v-if="searchStatus === 'loading'"
							class="search-status">
							{{ t('journalnotes', 'Searching…') }}
						</p>

						<p
							v-else-if="searchStatus === 'error'"
							class="search-status error">
							{{ t('journalnotes', 'Could not search Journal entries') }}
						</p>

						<p
							v-else-if="searchQuery.trim()
								&& searchStatus === 'loaded'"
							class="search-status">
							{{ filteredEntries.length }}
							{{ filteredEntries.length === 1
								? t('journalnotes', 'result')
								: t('journalnotes', 'results') }}
						</p>

						<div
							v-if="categoryStats.length"
							class="filter-group">
							<h3>{{ t('journalnotes', 'Categories') }}</h3>

							<div class="filter-list">
								<button
									v-for="item in categoryStats"
									:key="item.name"
									type="button"
									class="filter-chip category-filter"
									:class="{
										active: activeCategory === item.name,
									}"
									@click="toggleCategoryFilter(item.name)">
									{{ item.name }}
									<span>{{ item.count }}</span>
								</button>
							</div>
						</div>

						<div
							v-if="tagStats.length"
							class="filter-group">
							<h3>{{ t('journalnotes', 'Tags') }}</h3>

							<div class="filter-list">
								<button
									v-for="item in tagStats"
									:key="item.name"
									type="button"
									class="filter-chip tag-filter"
									:class="{
										active: activeTag === item.name,
									}"
									@click="toggleTagFilter(item.name)">
									#{{ item.name }}
									<span>{{ item.count }}</span>
								</button>
							</div>
						</div>

						<button
							v-if="hasActiveFilters"
							type="button"
							class="clear-filters"
							@click="clearFilters">
							Limpiar filtros
						</button>

						<p
							v-if="hasActiveFilters"
							class="filter-result-count">
							{{ filteredEntries.length }}
							{{ filteredEntries.length === 1 ? t('journalnotes', 'entry') : t('journalnotes', 'entries') }}
						</p>
					</section>


				</aside>

				<!-- Tercera columna: editor -->
				<main class="editor-panel">
					<Editor
						ref="editor"
						:date="date"
						@entry-edit="onEdit" />

				</main>

				<!-- Cuarta columna: información de la entrada actual -->
				<aside
					v-if="hasContent"
					class="inspector-panel"
					:class="{
						'inspector-panel--open': inspectorPanelOpen,
					}">
					<div class="inspector-header">
						<h2>{{ t('journalnotes', 'Information') }}</h2>

						<NcButton
							type="tertiary"
							class="responsive-panel-close"
							:aria-label="t('journalnotes', 'Close note information')"
							@click="closeResponsivePanels">
							<span
								class="icon-close"
								aria-hidden="true" />
						</NcButton>

						<span
							v-if="metadataStatus === 'saving'"
							class="metadata-status">
							Guardando…
						</span>

						<span
							v-else-if="metadataStatus === 'saved'"
							class="metadata-status saved">
							{{ t('journalnotes', 'Saved') }}
						</span>

						<span
							v-else-if="metadataStatus === 'error'"
							class="metadata-status error">
							{{ t('journalnotes', 'Could not save') }}
						</span>
					</div>

					<div class="organizer-section title-section">
						<label for="journal-title">
							{{ t('journalnotes', 'Title') }}
						</label>

						<input
							id="journal-title"
							v-model="noteTitle"
							type="text"
							maxlength="180"
							:placeholder="t('journalnotes', 'Give this note a title')"
							:disabled="!hasContent"
							@input="scheduleMetadataSave">

					</div>

					<div
						v-if="hasContent"
						class="organizer-section relations-section">
						<div class="relations-heading">
							<label>
								{{ t('journalnotes', 'Relations') }}
							</label>

							<span
								v-if="relationsStatus === 'loaded'"
								class="relations-count">
								{{
									visibleOutgoingRelations.length
										+ incomingRelations.length
								}}
							</span>
						</div>

						<p
							v-if="relationsStatus === 'loading'"
							class="organizer-help">
							{{ t('journalnotes', 'Loading relations…') }}
						</p>

						<p
							v-else-if="relationsStatus === 'error'"
							class="organizer-help tag-error">
							{{ t('journalnotes', 'Could not load relations.') }}
						</p>

						<template v-else-if="relationsStatus === 'loaded'">
							<div class="relations-group">
								<h4>
									{{ t('journalnotes', 'Links from this note') }}
								</h4>

								<p
									v-if="visibleOutgoingRelations.length === 0"
									class="organizer-help">
									{{ t('journalnotes', 'This note does not link to other notes yet.') }}
								</p>

								<div
									v-else
									class="relations-list">
									<button
										v-for="relation in visibleOutgoingRelations"
										:key="`outgoing-${relation.title}`"
										type="button"
										class="relation-item"
										:class="{
											'relation-item--missing': !relation.exists,
										}"
										@click="openOutgoingRelation(relation)">
										<div class="relation-item__header">
											<strong>{{ relation.title }}</strong>

											<span
												v-if="relation.exists"
												class="relation-status relation-status--exists">
												{{ t('journalnotes', 'Exists') }}
											</span>

											<span
												v-else
												class="relation-status relation-status--missing">
												{{ t('journalnotes', 'Not created') }}
											</span>
										</div>

										<small
											v-if="relation.exists && relation.date">
											{{ formatDate(relation.date) }}
										</small>

										<span
											v-if="relation.excerpt">
											{{ relation.excerpt }}
										</span>
									</button>
								</div>
							</div>

							<div class="relations-group">
								<h4>
									{{ t('journalnotes', 'Links to this note') }}
								</h4>

								<p
									v-if="incomingRelations.length === 0"
									class="organizer-help">
									{{ t('journalnotes', 'No other entries link to this note yet.') }}
								</p>

								<div
									v-else
									class="relations-list">
									<button
										v-for="relation in incomingRelations"
										:key="`incoming-${relation.date}-${relation.fileId || ''}`"
										type="button"
										class="relation-item"
										@click="onDateChange(relation.date)">
										<strong>
											{{ relation.title
												|| formatDate(relation.date) }}
										</strong>

										<small v-if="relation.title">
											{{ formatDate(relation.date) }}
										</small>

										<span>{{ relation.excerpt }}</span>
									</button>
								</div>
							</div>
						</template>
					</div>

					<div class="organizer-section">
						<label for="journal-category">
							{{ t('journalnotes', 'Categories') }}
						</label>

						<div class="tag-list">
							<button
								v-for="categoryItem in categories"
								:key="categoryItem"
								type="button"
								class="category-chip"
								:title="t('journalnotes', 'Remove category {category}', { category: categoryItem })"
								@click="removeCategory(categoryItem)">
								{{ categoryItem }}
								<span aria-hidden="true">×</span>
							</button>
						</div>

						<input
							id="journal-category"
							v-model="categoryInput"
							type="text"
							list="journal-category-suggestions"
							:placeholder="t('journalnotes', 'Type a category and press Enter')"
							:disabled="!hasContent"
							@keydown.enter.prevent="addCategory"
							@keydown.,.prevent="addCategory">

						<datalist id="journal-category-suggestions">
							<option
								v-for="item in categorySuggestions"
								:key="item"
								:value="item" />
						</datalist>

						<p class="organizer-help">
							{{ t('journalnotes', 'You can assign several categories.') }}
						</p>
					</div>

					<div class="organizer-section system-tags-section">
						<label for="journal-tag-input">
							{{ t('journalnotes', 'Tags') }}
						</label>

						<div class="tag-list">
							<button
								v-for="tag in selectedSystemTags"
								:key="tag.id"
								type="button"
								class="tag-chip"
								:title="t('journalnotes', 'Remove tag {tag}', { tag: tag.name })"
								:disabled="systemTagStatus === 'saving'"
								@click="removeSystemTag(tag)">
								#{{ tag.name }}
								<span aria-hidden="true">×</span>
							</button>
						</div>

						<div class="system-tag-search">
							<input
								id="journal-tag-input"
								v-model="tagInput"
								type="text"
								:placeholder="t('journalnotes', 'Search or create a tag')"
								autocomplete="off"
								:disabled="!hasContent
									|| systemTagStatus === 'loading'"
								@focus="showTagSuggestions = true"
								@input="showTagSuggestions = true"
								@keydown.enter.prevent="selectOrCreateSystemTag"
								@keydown.esc="showTagSuggestions = false">

							<div
								v-if="showTagSuggestions
									&& tagInput.trim()
									&& availableSystemTags.length"
								class="system-tag-suggestions">
								<button
									v-for="tag in availableSystemTags"
									:key="tag.id"
									type="button"
									@click="addExistingSystemTag(tag)">
									<span>#{{ tag.name }}</span>
									<small>Nextcloud</small>
								</button>
							</div>

							<div
								v-else-if="showTagSuggestions
									&& tagInput.trim()
									&& !exactSystemTagExists"
								class="system-tag-suggestions">
								<button
									type="button"
									class="create-system-tag"
									@click="createAndAddSystemTag">
									Crear “{{ normalizedTagInput }}”
								</button>
							</div>
						</div>

						<p
							v-if="systemTagStatus === 'saving'"
							class="organizer-help">
							{{ t('journalnotes', 'Saving tags…') }}
						</p>

						<p
							v-else-if="systemTagStatus === 'error'"
							class="organizer-help tag-error">
							{{ t('journalnotes', 'Could not save the tags.') }}
						</p>

					</div>

					<div
						v-if="!hasContent"
						class="empty-metadata-notice">
						{{ t('journalnotes', 'Write and save the entry before assigning categories and tags.') }}
					</div>				</aside>
			</div>
		</NcAppContent>
	</NcContent>
</template>

<script>
import {
	NcActionLink,
	NcAppContent,
	NcAppNavigation,
	NcAppNavigationIconBullet,
	NcAppNavigationItem,
	NcButton,
	NcContent,
	NcListItem,
} from '@nextcloud/vue'

import axios from '@nextcloud/axios'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'

import FilePdfBox from 'vue-material-design-icons/FilePdfBox'
import Markdown from 'vue-material-design-icons/LanguageMarkdown'

import Editor from './Editor'
import EntryInspector from './components/Inspector/EntryInspector'

export default {
	name: 'Journal',

	components: {
		Editor,
		EntryInspector,
		FilePdfBox,
		Markdown,
		NcActionLink,
		NcAppContent,
		NcAppNavigation,
		NcAppNavigationIconBullet,
		NcAppNavigationItem,
		NcButton,
		NcContent,
			NcListItem,
	},

	props: {
		date: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			baseUrl: generateUrl('apps/journalnotes'),
			pastEntriesAmount: 30,
			lastEntries: [],

			searchQuery: '',
			searchResults: [],
			searchStatus: null,
			searchTimeout: null,
			searchRequestId: 0,
			activeCategory: null,
			activeTag: null,

			explorePanelOpen: false,
			inspectorPanelOpen: false,
			responsiveKeydownHandler: null,

			currentEntryContent: '',
			noteTitle: '',
			categories: [],
			categoryInput: '',
			systemTagsCatalog: [],
			selectedSystemTags: [],
			tagInput: '',
			showTagSuggestions: false,
			systemTagStatus: null,
			metadataStatus: null,
			metadataTimeout: null,

			relations: {
				outgoing: [],
				incoming: [],
			},
			relationsStatus: null,
			relationsRequestId: 0,

			categorySuggestions: [
				t('journalnotes', 'Personal'),
				t('journalnotes', 'Work'),
				'Ideas',
				'Proyectos',
				t('journalnotes', 'Study'),
				'Finanzas',
				'Salud',
			],
		}
	},

	computed: {
		today() {
			return moment().format('YYYY-MM-DD')
		},

		formattedDate() {
			return this.formatDate(this.date)
		},

		showNextDayButton() {
			return moment(this.date)
				.add(1, 'day')
				.isSameOrBefore(moment(), 'day')
		},

		markdownDownloadLink() {
			return `${this.baseUrl}/export/markdown`
		},

		pdfDownloadLink() {
			return `${this.baseUrl}/export/pdf`
		},

		normalizedTagInput() {
			return this.tagInput
				.trim()
				.replace(/^#/, '')
		},

		exactSystemTagExists() {
			const query = this.normalizedTagInput.toLowerCase()

			if (!query) {
				return false
			}

			return this.systemTagsCatalog.some(
				tag => tag.name.toLowerCase() === query,
			)
		},

		availableSystemTags() {
			const query = this.normalizedTagInput.toLowerCase()
			const selectedIds = new Set(
				this.selectedSystemTags.map(tag => String(tag.id)),
			)

			return this.systemTagsCatalog
				.filter(tag => !selectedIds.has(String(tag.id)))
				.filter(tag => !query
					|| tag.name.toLowerCase().includes(query))
				.slice(0, 12)
		},

		categoryStats() {
			const counts = new Map()

			for (const entry of this.lastEntries) {
				const categories = Array.isArray(entry.categories)
					? entry.categories
					: []

				for (const category of categories) {
					const name = String(category).trim()

					if (name) {
						counts.set(name, (counts.get(name) || 0) + 1)
					}
				}
			}

			return [...counts.entries()]
				.map(([name, count]) => ({ name, count }))
				.sort((a, b) => {
					if (b.count !== a.count) {
						return b.count - a.count
					}

					return a.name.localeCompare(b.name)
				})
		},

		tagStats() {
			const counts = new Map()

			for (const entry of this.lastEntries) {
				const tags = Array.isArray(entry.tags)
					? entry.tags
					: []

				for (const tag of tags) {
					const name = String(tag).trim().toLowerCase()

					if (name) {
						counts.set(name, (counts.get(name) || 0) + 1)
					}
				}
			}

			return [...counts.entries()]
				.map(([name, count]) => ({ name, count }))
				.sort((a, b) => {
					if (b.count !== a.count) {
						return b.count - a.count
					}

					return a.name.localeCompare(b.name)
				})
		},

		filteredEntries() {
			const query = this.searchQuery.trim()

			const sourceEntries = query
				? this.searchResults
				: this.lastEntries

			return sourceEntries.filter(entry => {
				const categories = Array.isArray(entry.categories)
					? entry.categories
					: []

				const tags = Array.isArray(entry.tags)
					? entry.tags
					: []

				const matchesCategory = !this.activeCategory
					|| categories.some(category =>
						String(category).toLowerCase()
							=== this.activeCategory.toLowerCase(),
					)

				const matchesTag = !this.activeTag
					|| tags.some(tag =>
						String(tag).toLowerCase()
							=== this.activeTag.toLowerCase(),
					)

				return matchesCategory && matchesTag
			})
		},

		hasActiveFilters() {
			return Boolean(
				this.searchQuery
				|| this.activeCategory
				|| this.activeTag,
			)
		},

		outgoingRelations() {
			return Array.isArray(this.relations.outgoing)
				? this.relations.outgoing
				: []
		},

		incomingRelations() {
			return Array.isArray(this.relations.incoming)
				? this.relations.incoming.filter(
					entry => entry.date !== this.date,
				)
				: []
		},

		visibleOutgoingRelations() {
			return this.outgoingRelations.filter(
				relation => relation.date !== this.date,
			)
		},

		hasRelations() {
			return this.visibleOutgoingRelations.length > 0
				|| this.incomingRelations.length > 0
		},

		hasContent() {
			return this.currentEntryContent.trim() !== ''
		},
	},

	watch: {
		date: {
			immediate: true,
			handler() {
				this.closeResponsivePanels()
				this.fetchCurrentEntry()
			},
		},

		searchQuery() {
			this.scheduleGlobalSearch()
		},
	},

	mounted() {
		this.fetchPastEntries()
		this.fetchSystemTagsCatalog()

		this.responsiveKeydownHandler = event => {
			this.handleResponsiveKeydown(event)
		}

		window.addEventListener(
			'keydown',
			this.responsiveKeydownHandler,
		)
	},

	beforeUnmount() {
		clearTimeout(this.metadataTimeout)
		clearTimeout(this.searchTimeout)

		if (this.responsiveKeydownHandler) {
			window.removeEventListener(
				'keydown',
				this.responsiveKeydownHandler,
			)
		}

		this.responsiveKeydownHandler = null
	},

	methods: {
		openExplorePanel() {
			this.inspectorPanelOpen = false
			this.explorePanelOpen = true
		},

		openInspectorPanel() {
			this.explorePanelOpen = false
			this.inspectorPanelOpen = true
		},

		closeResponsivePanels() {
			this.explorePanelOpen = false
			this.inspectorPanelOpen = false
		},

		handleResponsiveKeydown(event) {
			if (event.key === 'Escape') {
				this.closeResponsivePanels()
			}
		},

		onDateChange(value) {
			const parsedDate = moment(value)

			if (!parsedDate.isValid()) {
				return
			}

			const targetDate = parsedDate.format('YYYY-MM-DD')
			if (targetDate === this.date) {
				return
			}

			this.$router.push({
				name: 'date',
				params: {
					date: targetDate,
				},
			})
		},

		openCalendar() {
			const picker = this.$refs.nativeDatePicker

			if (!picker) {
				return
			}

			if (typeof picker.showPicker === 'function') {
				picker.showPicker()
			} else {
				picker.click()
			}
		},

		onNativeDateChange(event) {
			const value = event.target.value

			if (value) {
				this.onDateChange(value)
			}
		},

		goPrevDay() {
			this.onDateChange(
				moment(this.date).subtract(1, 'day'),
			)
		},

		goNextDay() {
			this.onDateChange(
				moment(this.date).add(1, 'day'),
			)
		},

		isCurrentDate(entryDate) {
			return this.date === entryDate
		},

		formatDate(entryDate) {
			return moment(entryDate).format('LL')
		},

		scheduleGlobalSearch() {
			clearTimeout(this.searchTimeout)

			const query = this.searchQuery.trim()

			if (!query) {
				this.searchResults = []
				this.searchStatus = null
				return
			}

			this.searchStatus = 'loading'

			this.searchTimeout = window.setTimeout(() => {
				this.fetchGlobalSearch(query)
			}, 350)
		},

		async fetchGlobalSearch(query) {
			const requestId = ++this.searchRequestId

			try {
				const response = await axios.get(
					generateUrl('apps/journalnotes/search'),
					{
						params: {
							q: query,
							limit: 100,
						},
					},
				)

				if (
					requestId !== this.searchRequestId
					|| query !== this.searchQuery.trim()
				) {
					return
				}

				this.searchResults = Array.isArray(response.data)
					? response.data
					: []

				this.searchStatus = 'loaded'
			} catch (error) {
				if (requestId !== this.searchRequestId) {
					return
				}

				this.searchResults = []
				this.searchStatus = 'error'

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not search Journal entries'),
					error,
				)
			}
		},

		async fetchPastEntries() {
			try {
				const response = await axios.get(
					generateUrl(
						`apps/journalnotes/entries/${this.pastEntriesAmount}`,
					),
				)

				this.lastEntries = Array.isArray(response.data)
					? response.data
					: []
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not load recent entries'),
					error,
				)
			}
		},

		async fetchCurrentEntry() {
			clearTimeout(this.metadataTimeout)
			this.metadataStatus = null
			this.tagInput = ''

			try {
				const response = await axios.get(
					generateUrl(`apps/journalnotes/entry/${this.date}`),
				)

				this.currentEntryContent
					= response.data.entryContent || ''

				const metadata = response.data.metadata || {}

				this.noteTitle = typeof metadata.title === 'string'
					? metadata.title.trim()
					: ''

				if (Array.isArray(metadata.categories)) {
					this.categories = [...new Set(
						metadata.categories
							.map(item => String(item).trim())
							.filter(Boolean),
					)]
				} else if (
					typeof metadata.category === 'string'
					&& metadata.category.trim() !== ''
				) {
					this.categories = [metadata.category.trim()]
				} else {
					this.categories = []
				}

				await this.fetchEntrySystemTags(this.date)
				await this.fetchRelations(this.date)
			} catch (error) {
				this.currentEntryContent = ''
				this.noteTitle = ''
				this.categories = []
				this.categoryInput = ''
				this.selectedSystemTags = []
				this.backlinks = []
				this.backlinksStatus = null

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not load the entry organization'),
					error,
				)
			}
		},

		async fetchRelations(entryDate = this.date) {
			const requestId = ++this.relationsRequestId

			if (!entryDate) {
				this.relations = {
					outgoing: [],
					incoming: [],
				}
				this.relationsStatus = null
				return
			}

			this.relationsStatus = 'loading'

			try {
				const response = await axios.get(
					generateUrl('apps/journalnotes/relations'),
					{
						params: {
							date: entryDate,
							limit: 100,
						},
					},
				)

				if (
					requestId !== this.relationsRequestId
					|| entryDate !== this.date
				) {
					return
				}

				const data = response.data || {}

				this.relations = {
					outgoing: Array.isArray(data.outgoing)
						? data.outgoing
						: [],
					incoming: Array.isArray(data.incoming)
						? data.incoming
						: [],
				}

				this.relationsStatus = 'loaded'
			} catch (error) {
				if (requestId !== this.relationsRequestId) {
					return
				}

				this.relations = {
					outgoing: [],
					incoming: [],
				}
				this.relationsStatus = 'error'

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not load relations'),
					error,
				)
			}
		},

		async onEdit(entryDate, content) {
			if (entryDate === this.date) {
				this.currentEntryContent = content || ''
			}

			const entryIndex = this.lastEntries.findIndex(
				entry => entry.date === entryDate,
			)

			if (!content) {
				if (entryIndex !== -1) {
					this.lastEntries.splice(entryIndex, 1)
				}

				if (entryDate === this.date) {
					await this.fetchRelations(entryDate)
				}

				return
			}

			const excerpt = content
				.replace(/\s+/g, ' ')
				.trim()
				.substring(0, 40)

			if (entryIndex === -1) {
				this.lastEntries.unshift({
					date: entryDate,
					excerpt,
				})
			} else {
				this.lastEntries[entryIndex].excerpt = excerpt
			}

			/*
			 * El backend ya guardó el Markdown. Volvemos a consultar
			 * Relaciones para reflejar nuevos wikilinks sin recargar.
			 */
			if (entryDate === this.date) {
				await this.fetchRelations(entryDate)
			}
		},

		openOutgoingRelation(relation) {
			if (!relation) {
				return
			}

			if (relation.exists && relation.date) {
				this.onDateChange(relation.date)
				return
			}

			const title = String(relation.title || '').trim()

			if (!title) {
				return
			}

			const editor = this.$refs.editor

			if (
				editor
				&& typeof editor.showCreateLinkedNote === 'function'
			) {
				editor.showCreateLinkedNote(title)
			}
		},

		toggleCategoryFilter(category) {
			this.activeCategory = this.activeCategory === category
				? null
				: category
		},

		toggleTagFilter(tag) {
			this.activeTag = this.activeTag === tag
				? null
				: tag
		},

		clearFilters() {
			this.searchQuery = ''
			this.activeCategory = null
			this.activeTag = null
		},

		addCategory() {
			const category = this.categoryInput.trim()
			this.categoryInput = ''

			if (
				!category
				|| this.categories.some(
					item => item.toLowerCase() === category.toLowerCase(),
				)
			) {
				return
			}

			this.categories.push(category)
			this.scheduleMetadataSave()
		},

		removeCategory(category) {
			this.categories = this.categories.filter(
				item => item !== category,
			)
			this.scheduleMetadataSave()
		},

		async fetchSystemTagsCatalog() {
			try {
				const response = await axios.get(
					generateUrl('apps/journalnotes/system-tags'),
				)

				this.systemTagsCatalog = Array.isArray(response.data)
					? response.data
					: []
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not load the tag catalog'),
					error,
				)
			}
		},

		async fetchEntrySystemTags(entryDate) {
			this.systemTagStatus = 'loading'

			try {
				const response = await axios.get(
					generateUrl(
						`apps/journalnotes/entry/${entryDate}/system-tags`,
					),
				)

				if (entryDate !== this.date) {
					return
				}

				this.selectedSystemTags = Array.isArray(response.data)
					? response.data
					: []

				this.systemTagStatus = null
			} catch (error) {
				if (entryDate === this.date) {
					this.selectedSystemTags = []
					this.systemTagStatus = 'error'
				}

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not load the entry tags'),
					error,
				)
			}
		},

		async selectOrCreateSystemTag() {
			const query = this.normalizedTagInput

			if (!query) {
				return
			}

			const existing = this.systemTagsCatalog.find(
				tag => tag.name.toLowerCase() === query.toLowerCase(),
			)

			if (existing) {
				await this.addExistingSystemTag(existing)
			} else {
				await this.createAndAddSystemTag()
			}
		},

		async addExistingSystemTag(tag) {
			if (
				!tag
				|| this.selectedSystemTags.some(
					item => String(item.id) === String(tag.id),
				)
			) {
				this.tagInput = ''
				this.showTagSuggestions = false
				return
			}

			this.selectedSystemTags.push(tag)
			this.tagInput = ''
			this.showTagSuggestions = false

			await this.saveSystemTagRelations()
		},

		async createAndAddSystemTag() {
			const name = this.normalizedTagInput

			if (!name) {
				return
			}

			this.systemTagStatus = 'saving'

			try {
				const response = await axios.post(
					generateUrl('apps/journalnotes/system-tags'),
					{ name },
				)

				const tag = response.data

				if (
					tag
					&& !this.systemTagsCatalog.some(
						item => String(item.id) === String(tag.id),
					)
				) {
					this.systemTagsCatalog.push(tag)
					this.systemTagsCatalog.sort(
						(a, b) => a.name.localeCompare(b.name),
					)
				}

				await this.addExistingSystemTag(tag)
			} catch (error) {
				this.systemTagStatus = 'error'

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not create the global tag'),
					error,
				)
			}
		},

		async removeSystemTag(tag) {
			this.selectedSystemTags = this.selectedSystemTags.filter(
				item => String(item.id) !== String(tag.id),
			)

			await this.saveSystemTagRelations()
		},

		async saveSystemTagRelations() {
			if (!this.hasContent) {
				return
			}

			const entryDate = this.date
			this.systemTagStatus = 'saving'

			try {
				const response = await axios.put(
					generateUrl(
						`apps/journalnotes/entry/${entryDate}/system-tags`,
					),
					{
						tagIds: this.selectedSystemTags.map(
							tag => String(tag.id),
						),
					},
				)

				if (entryDate !== this.date) {
					return
				}

				this.selectedSystemTags = Array.isArray(response.data)
					? response.data
					: []

				this.systemTagStatus = null

				/*
				 * Actualiza categorías y el índice de nombres utilizado
				 * por los filtros del diario.
				 */
				await this.saveMetadata()
			} catch (error) {
				this.systemTagStatus = 'error'

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not save the global tags'),
					error,
				)
			}
		},

		scheduleMetadataSave() {
			if (!this.hasContent) {
				return
			}

			this.metadataStatus = 'saving'
			clearTimeout(this.metadataTimeout)

			this.metadataTimeout = setTimeout(() => {
				this.saveMetadata()
			}, 600)
		},

		async saveMetadata() {
			if (!this.hasContent) {
				return
			}

			const entryDate = this.date
			const metadata = {
				title: this.noteTitle.trim(),
				categories: this.categories,
				/*
				 * Copia de búsqueda. La relación oficial se guarda
				 * mediante ISystemTagObjectMapper.
				 */
				tags: this.selectedSystemTags.map(tag => tag.name),
			}

			try {
				await axios.put(
					generateUrl(`apps/journalnotes/entry/${entryDate}`),
					{
						content: this.currentEntryContent,
						metadataJson: JSON.stringify(metadata),
					},
				)

				if (entryDate !== this.date) {
					return
				}

				this.metadataStatus = 'saved'

				await this.fetchRelations(entryDate)

				const entry = this.lastEntries.find(
					item => item.date === entryDate,
				)

				if (entry) {
					entry.title = this.noteTitle.trim()
					entry.categories = [...this.categories]
					entry.tags = this.selectedSystemTags.map(
						tag => tag.name,
					)
				}

				window.setTimeout(() => {
					if (
						entryDate === this.date
						&& this.metadataStatus === 'saved'
					) {
						this.metadataStatus = null
					}
				}, 1500)
			} catch (error) {
				this.metadataStatus = 'error'

				// eslint-disable-next-line no-console
				console.error(
					t('journalnotes', 'Could not save categories and tags'),
					error,
				)
			}
		},
	},
}
</script>

<style lang="scss">
#journal-content {
	display: flex;
	width: 100% !important;
	max-width: none !important;
	height: calc(100% - 50px);
	margin: 0;

	.dates-navigation {
		flex: 0 0 300px;
		width: 300px;
	}

	.navigation-wrapper {
		display: flex;
		align-items: center;
		gap: 4px;
		padding: 12px;

		.native-date-picker {
			position: absolute;
			width: 1px;
			height: 1px;
			opacity: 0;
			pointer-events: none;
		}

		.open-calendar {
			flex: 1 1 auto;
			min-width: 0;
			font-size: 14px;
		}
	}

	.export {
		padding: 12px;
	}

	.journal-app-content {
		flex: 1 1 auto !important;
		width: auto !important;
		max-width: none !important;
		min-width: 0 !important;
	}

	.journal-workspace {
		display: grid;
		grid-template-columns:
			250px
			minmax(0, 1fr)
			290px;
		width: 100%;
		height: 100%;
		min-width: 0;
	}

	.journal-workspace--welcome {
		grid-template-columns:
			250px
			minmax(0, 1fr);
	}

	.organizer-panel {
		min-width: 0;
		padding: 20px 16px;
		overflow-y: auto;
		border-right: 1px solid var(--color-border);
		background: var(--color-main-background);
	}





	.organizer-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		margin-bottom: 24px;

		h2 {
			margin: 0;
			font-size: 18px;
		}
	}

	.editor-panel {
		width: 100%;
		min-width: 0;
		height: 100%;
		overflow: hidden;
	}

	.app-content,
	.app-content__main {
		flex: 1 1 auto !important;
		width: 100% !important;
		max-width: none !important;
		min-width: 0 !important;
	}

	/*
	 * El botón de apertura/cierre de NcAppContent se superpone
	 * en el borde izquierdo. Dejamos espacio para que no tape el título.
	 */
	.organizer-header {
		padding-left: 28px;
	}

	.global-filters {
		margin-bottom: 24px;
		padding-bottom: 22px;
		border-bottom: 1px solid var(--color-border);

		> label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
		}

		> input {
			width: 100%;
			min-height: 38px;
			padding: 7px 10px;
			border: 1px solid var(--color-border-maxcontrast);
			border-radius: var(--border-radius-large);
			background: var(--color-main-background);
			color: var(--color-main-text);
			box-sizing: border-box;
		}
	}

	.filter-group {
		margin-top: 18px;

		h3 {
			margin: 0 0 8px;
			font-size: 13px;
			color: var(--color-text-maxcontrast);
		}
	}

	.filter-list {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}

	.filter-chip {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		min-height: 28px;
		padding: 3px 8px;
		border: 1px solid var(--color-border);
		border-radius: 14px;
		background: var(--color-background-hover);
		color: var(--color-main-text);
		cursor: pointer;

		span {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 18px;
			height: 18px;
			padding: 0 4px;
			border-radius: 9px;
			background: var(--color-background-dark);
			font-size: 11px;
		}

		&:hover,
		&.active {
			border-color: var(--color-primary-element);
			background: var(--color-primary-element);
			color: var(--color-primary-element-text);
		}
	}

	.clear-filters {
		margin-top: 14px;
		padding: 5px 10px;
		border: 0;
		border-radius: var(--border-radius);
		background: var(--color-background-hover);
		color: var(--color-main-text);
		cursor: pointer;
	}

	.filter-result-count {
		display: inline-block;
		margin: 14px 0 0 8px;
		font-size: 12px;
		color: var(--color-text-maxcontrast);
	}

	.current-entry-heading {
		margin: 8px 0 16px;
		padding-bottom: 8px;
		border-bottom: 1px solid var(--color-border);

		h3 {
			margin: 0;
			font-size: 15px;
			font-weight: 600;
		}
	}

	.organizer-group-heading {
		margin-top: 32px;
	}

	.responsive-panel-toolbar {
		display: none;
		position: absolute;
		top: 0;
		right: 0;
		left: 0;
		z-index: 350;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		height: 48px;
		padding: 4px 10px;
		border-bottom: 1px solid var(--color-border);
		background: var(--color-main-background);
		box-sizing: border-box;
	}

	.responsive-panel-button {
		min-width: 0;
	}

	.responsive-panel-button span {
		margin-right: 6px;
	}

	.responsive-panel-close {
		display: none;
		flex-shrink: 0;
	}

	.responsive-panel-backdrop {
		position: absolute;
		inset: 0;
		z-index: 450;
		padding: 0;
		border: 0;
		background: rgb(0 0 0 / 28%);
		cursor: default;
	}

	@media (max-width: 1050px) {
		.responsive-panel-close {
			display: inline-flex;
		}
	}

	@media (max-width: 1400px) {
		.journal-workspace {
			grid-template-columns:
				220px
				minmax(0, 1fr)
				280px;
		}
	}

	@media (max-width: 1050px) {
		.journal-workspace {
			grid-template-columns:
				minmax(0, 1fr)
				280px;
			padding-top: 48px;
		}

		.organizer-panel {
			display: block;
			position: absolute;
			top: 0;
			bottom: 0;
			left: 0;
			z-index: 500;
			width: min(320px, 88vw);
			transform: translateX(-105%);
			box-shadow: 6px 0 24px rgb(0 0 0 / 20%);
			transition: transform 160ms ease;
		}

		.organizer-panel--open {
			transform: translateX(0);
		}

		.dates-navigation {
			flex-basis: 270px;
			width: 270px;
		}

		.responsive-panel-toolbar {
			display: flex;
		}
	}

	@media (max-width: 800px) {
		.journal-workspace,
		.journal-workspace--welcome {
			display: block;
			padding-top: 48px;
		}

	}

	@media (prefers-reduced-motion: reduce) {
		.responsive-panel-backdrop {
			transition: none;
		}
	}
}

.search-status {
	margin: 6px 0 12px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);

	&.error {
		color: var(--color-error);
	}
}


</style>
