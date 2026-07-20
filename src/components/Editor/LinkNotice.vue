<template>
	<div
		v-if="message"
		class="link-notice"
		role="status">
		<span>{{ message }}</span>

		<div
			v-if="pendingTitle"
			class="link-notice__actions">
			<input
				:value="date"
				type="date"
				class="link-notice__date"
				:max="today"
				:aria-label="t(
					'journalnotes',
					'Date for the new linked note',
				)"
				@input="updateDate">

			<button
				type="button"
				class="primary"
				:disabled="creating"
				@click="$emit('create')">
				{{
					creating
						? t('journalnotes', 'Creating note…')
						: t('journalnotes', 'Create note')
				}}
			</button>

			<button
				type="button"
				:disabled="creating"
				@click="$emit('cancel')">
				{{ t('journalnotes', 'Cancel') }}
			</button>
		</div>
	</div>
</template>

<script>
export default {
	name: 'LinkNotice',

	props: {
		message: {
			type: String,
			default: '',
		},

		pendingTitle: {
			type: String,
			default: '',
		},

		date: {
			type: String,
			default: '',
		},

		today: {
			type: String,
			required: true,
		},

		creating: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'update:date',
		'create',
		'cancel',
	],

	methods: {
		updateDate(event) {
			this.$emit(
				'update:date',
				String(event.target?.value || ''),
			)
		},
	},
}
</script>

<style lang="scss" src="./link-notice.scss"></style>
