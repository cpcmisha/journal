<template>
	<div class="organizer-section title-section">
		<label for="journal-title">
			{{ t('journalnotes', 'Title') }}
		</label>

		<input
			id="journal-title"
			:value="modelValue"
			type="text"
			maxlength="180"
			:placeholder="t('journalnotes', 'Give this note a title')"
			:disabled="disabled"
			@input="handleInput">
	</div>
</template>

<script>
export default {
	name: 'InspectorTitle',

	props: {
		modelValue: {
			type: String,
			default: '',
		},

		disabled: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:modelValue',
		'change',
	],

	methods: {
		handleInput(event) {
			this.$emit(
				'update:modelValue',
				String(event.target?.value || ''),
			)

			this.$emit('change')
		},
	},
}
</script>
