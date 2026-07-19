<template>
	<div class="organizer-section categories-section">
		<label for="journal-category">
			{{ t('journalnotes', 'Categories') }}
		</label>

		<div class="tag-list">
			<button
				v-for="category in categories"
				:key="category"
				type="button"
				class="category-chip"
				:title="t(
					'journalnotes',
					'Remove category {category}',
					{ category },
				)"
				@click="$emit('remove', category)">
				{{ category }}
				<span aria-hidden="true">×</span>
			</button>
		</div>

		<input
			id="journal-category"
			:value="inputValue"
			type="text"
			list="journal-category-suggestions"
			:placeholder="t(
				'journalnotes',
				'Type a category and press Enter',
			)"
			:disabled="disabled"
			@input="$emit(
				'update:inputValue',
				String($event.target?.value || ''),
			)"
			@keydown.enter.prevent="$emit('add')"
			@keydown.,.prevent="$emit('add')">

		<datalist id="journal-category-suggestions">
			<option
				v-for="suggestion in suggestions"
				:key="suggestion"
				:value="suggestion" />
		</datalist>

		<p class="organizer-help">
			{{
				t(
					'journalnotes',
					'You can assign several categories.',
				)
			}}
		</p>
	</div>
</template>

<script>
export default {
	name: 'InspectorCategories',

	props: {
		categories: {
			type: Array,
			default: () => [],
		},

		inputValue: {
			type: String,
			default: '',
		},

		suggestions: {
			type: Array,
			default: () => [],
		},

		disabled: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:inputValue',
		'add',
		'remove',
	],
}
</script>
