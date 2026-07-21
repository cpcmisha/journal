<template>
	<div class="organizer-section relations-section">
		<div class="relations-heading">
			<label>
				{{ t('journalnotes', 'Relations') }}
			</label>

			<span
				v-if="status === 'loaded'"
				class="relations-count">
				{{ outgoing.length + incoming.length }}
			</span>
		</div>

		<p
			v-if="status === 'loading'"
			class="organizer-help">
			{{ t('journalnotes', 'Loading relations…') }}
		</p>

		<p
			v-else-if="status === 'error'"
			class="organizer-help tag-error">
			{{ t('journalnotes', 'Could not load relations.') }}
		</p>

		<template v-else-if="status === 'loaded'">
			<div class="relations-group">
				<h4>
					{{ t('journalnotes', 'Links from this note') }}
				</h4>

				<p
					v-if="outgoing.length === 0"
					class="organizer-help">
					{{
						t(
							'journalnotes',
							'This note does not link to other notes yet.',
						)
					}}
				</p>

				<div
					v-else
					class="relations-list">
					<button
						v-for="relation in outgoing"
						:key="`outgoing-${relation.title}`"
						type="button"
						class="relation-item"
						:class="{
							'relation-item--missing':
								!relation.exists,
						}"
						@click="$emit(
							'open-outgoing',
							relation,
						)">
						<div class="relation-item__header">
							<strong>{{ relation.title }}</strong>

							<span
								v-if="relation.exists"
								class="
									relation-status
									relation-status--exists
								">
								{{ t('journalnotes', 'Exists') }}
							</span>

							<span
								v-else
								class="
									relation-status
									relation-status--missing
								">
								{{ t('journalnotes', 'Not created') }}
							</span>
						</div>

						<small
							v-if="relation.exists
								&& relation.date">
							{{ formatEntryDate(relation.date) }}
						</small>

						<span v-if="relation.excerpt">
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
					v-if="incoming.length === 0"
					class="organizer-help">
					{{
						t(
							'journalnotes',
							'No other entries link to this note yet.',
						)
					}}
				</p>

				<div
					v-else
					class="relations-list">
					<button
						v-for="relation in incoming"
						:key="`incoming-${relation.date}-${relation.fileId || ''}`"
						type="button"
						class="relation-item"
						@click="$emit(
							'open-incoming',
							relation.date,
						)">
						<strong>
							{{
								relation.title
									|| formatEntryDate(
										relation.date,
									)
							}}
						</strong>

						<small v-if="relation.title">
							{{ formatEntryDate(relation.date) }}
						</small>

						<span>{{ relation.excerpt }}</span>
					</button>
				</div>
			</div>
		</template>
	</div>
</template>

<script>
export default {
	name: 'InspectorRelations',

	props: {
		status: {
			type: String,
			default: null,
		},

		outgoing: {
			type: Array,
			default: () => [],
		},

		incoming: {
			type: Array,
			default: () => [],
		},

		formatDate: {
			type: Function,
			required: true,
		},
	},

	emits: [
		'open-outgoing',
		'open-incoming',
	],

	methods: {
		formatEntryDate(date) {
			return this.formatDate(date)
		},
	},
}
</script>
