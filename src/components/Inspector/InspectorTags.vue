<template>
	<div class="organizer-section system-tags-section">
		<label for="journal-tag-input">
			{{ t('journalnotes', 'Tags') }}
		</label>

		<div class="tag-list">
			<button
				v-for="tag in selectedTags"
				:key="tag.id"
				type="button"
				class="tag-chip"
				:title="t(
					'journalnotes',
					'Remove tag {tag}',
					{ tag: tag.name },
				)"
				:disabled="status === 'saving'"
				@click="$emit('remove', tag)">
				#{{ tag.name }}
				<span aria-hidden="true">×</span>
			</button>
		</div>

		<div class="system-tag-search">
			<input
				id="journal-tag-input"
				:value="inputValue"
				type="text"
				:placeholder="t(
					'journalnotes',
					'Search or create a tag',
				)"
				autocomplete="off"
				:disabled="disabled || status === 'loading'"
				@focus="$emit('update:showSuggestions', true)"
				@input="handleInput"
				@keydown.enter.prevent="$emit('select-or-create')"
				@keydown.esc="$emit(
					'update:showSuggestions',
					false,
				)">

			<div
				v-if="showSuggestions
					&& inputValue.trim()
					&& availableTags.length"
				class="system-tag-suggestions">
				<button
					v-for="tag in availableTags"
					:key="tag.id"
					type="button"
					@click="$emit('add-existing', tag)">
					<span>#{{ tag.name }}</span>
					<small>Nextcloud</small>
				</button>
			</div>

			<div
				v-else-if="showSuggestions
					&& inputValue.trim()
					&& !exactTagExists"
				class="system-tag-suggestions">
				<button
					type="button"
					class="create-system-tag"
					@click="$emit('create')">
					Crear “{{ normalizedInput }}”
				</button>
			</div>
		</div>

		<p
			v-if="status === 'saving'"
			class="organizer-help">
			{{ t('journalnotes', 'Saving tags…') }}
		</p>

		<p
			v-else-if="status === 'error'"
			class="organizer-help tag-error">
			{{ t('journalnotes', 'Could not save the tags.') }}
		</p>
	</div>
</template>

<script>
export default {
	name: 'InspectorTags',

	props: {
		selectedTags: {
			type: Array,
			default: () => [],
		},

		inputValue: {
			type: String,
			default: '',
		},

		showSuggestions: {
			type: Boolean,
			default: false,
		},

		status: {
			type: String,
			default: null,
		},

		availableTags: {
			type: Array,
			default: () => [],
		},

		exactTagExists: {
			type: Boolean,
			default: false,
		},

		normalizedInput: {
			type: String,
			default: '',
		},

		disabled: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:inputValue',
		'update:showSuggestions',
		'select-or-create',
		'add-existing',
		'create',
		'remove',
	],

	methods: {
		handleInput(event) {
			this.$emit(
				'update:inputValue',
				String(event.target?.value || ''),
			)

			this.$emit(
				'update:showSuggestions',
				true,
			)
		},
	},
}
</script>
