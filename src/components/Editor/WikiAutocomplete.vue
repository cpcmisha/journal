<template>
	<div
		v-if="visible"
		class="wiki-autocomplete"
		role="listbox"
		:aria-label="t(
			'journalnotes',
			'Linked note suggestions',
		)">
		<div class="wiki-autocomplete__header">
			<span>{{ t('journalnotes', 'Link to a note') }}</span>
			<small>[[{{ query }}</small>
		</div>

		<button
			v-for="(suggestion, index) in suggestions"
			:key="`${suggestion.date}-${suggestion.title}`"
			type="button"
			class="wiki-autocomplete__item"
			:class="{
				'wiki-autocomplete__item--active':
					index === selectedIndex,
			}"
			role="option"
			:aria-selected="index === selectedIndex"
			@pointerdown.prevent="$emit(
				'select',
				suggestion,
			)">
			<strong>{{ suggestion.title }}</strong>

			<small v-if="suggestion.date">
				{{ formatDate(suggestion.date) }}
			</small>

			<span v-if="suggestion.excerpt">
				{{ cleanExcerpt(suggestion.excerpt) }}
			</span>
		</button>

		<div
			v-if="loading"
			class="wiki-autocomplete__empty">
			{{ t('journalnotes', 'Searching notes…') }}
		</div>

		<button
			v-else-if="query && suggestions.length === 0"
			type="button"
			class="wiki-autocomplete__create"
			@pointerdown.prevent="$emit('create', query)">
			{{
				t(
					'journalnotes',
					'Create note: {title}',
					{ title: query },
				)
			}}
		</button>
	</div>
</template>

<script>
export default {
	name: 'WikiAutocomplete',

	props: {
		visible: {
			type: Boolean,
			default: false,
		},

		query: {
			type: String,
			default: '',
		},

		loading: {
			type: Boolean,
			default: false,
		},

		suggestions: {
			type: Array,
			default: () => [],
		},

		selectedIndex: {
			type: Number,
			default: 0,
		},

		formatDate: {
			type: Function,
			required: true,
		},

		cleanExcerpt: {
			type: Function,
			required: true,
		},
	},

	emits: [
		'select',
		'create',
	],
}
</script>

<style lang="scss" src="./wiki-autocomplete.scss"></style>
