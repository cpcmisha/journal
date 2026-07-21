<template>
	<aside
		class="organizer-panel"
		:class="{
			'organizer-panel--open': open,
		}">
		<div class="organizer-header">
			<h2>{{ t('journalnotes', 'Explore') }}</h2>

			<NcButton
				type="tertiary"
				class="responsive-panel-close"
				:aria-label="t(
					'journalnotes',
					'Close Explore panel',
				)"
				@click="$emit('close')">
				<Close :size="20" />
			</NcButton>
		</div>

		<section class="global-filters">
			<label for="journal-search">
				{{ t('journalnotes', 'Search notes') }}
			</label>

			<input
				id="journal-search"
				:value="searchQuery"
				type="search"
				:placeholder="t(
					'journalnotes',
					'Search dates and excerpts',
				)"
				@input="handleSearchInput">

			<p
				v-if="searchStatus === 'loading'"
				class="search-status">
				{{ t('journalnotes', 'Searching…') }}
			</p>

			<p
				v-else-if="searchStatus === 'error'"
				class="search-status error">
				{{
					t(
						'journalnotes',
						'Could not search Journal entries',
					)
				}}
			</p>

			<p
				v-else-if="searchQuery.trim()
					&& searchStatus === 'loaded'"
				class="search-status">
				{{ resultCount }}
				{{
					resultCount === 1
						? t('journalnotes', 'result')
						: t('journalnotes', 'results')
				}}
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
							active:
								activeCategory === item.name,
						}"
						@click="$emit(
							'toggle-category',
							item.name,
						)">
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
						@click="$emit(
							'toggle-tag',
							item.name,
						)">
						#{{ item.name }}
						<span>{{ item.count }}</span>
					</button>
				</div>
			</div>

			<button
				v-if="hasActiveFilters"
				type="button"
				class="clear-filters"
				@click="$emit('clear-filters')">
				{{ t('journalnotes', 'Clear filters') }}
			</button>

			<p
				v-if="hasActiveFilters"
				class="filter-result-count">
				{{ resultCount }}
				{{
					resultCount === 1
						? t('journalnotes', 'entry')
						: t('journalnotes', 'entries')
				}}
			</p>
		</section>
	</aside>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import Close from 'vue-material-design-icons/Close.vue'

export default {
	name: 'ExplorePanel',

	components: {
		Close,
		NcButton,
	},

	props: {
		open: {
			type: Boolean,
			default: false,
		},

		searchQuery: {
			type: String,
			default: '',
		},

		searchStatus: {
			type: String,
			default: null,
		},

		categoryStats: {
			type: Array,
			default: () => [],
		},

		tagStats: {
			type: Array,
			default: () => [],
		},

		activeCategory: {
			type: String,
			default: null,
		},

		activeTag: {
			type: String,
			default: null,
		},

		resultCount: {
			type: Number,
			default: 0,
		},

		hasActiveFilters: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'close',
		'update-search-query',
		'toggle-category',
		'toggle-tag',
		'clear-filters',
	],

	methods: {
		handleSearchInput(event) {
			this.$emit(
				'update-search-query',
				String(event.target?.value || ''),
			)
		},
	},
}
</script>

<style lang="scss" src="./explore.scss"></style>
