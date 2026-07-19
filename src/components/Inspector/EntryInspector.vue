<template>
	<aside
		v-if="hasContent"
		class="inspector-panel"
		:class="{
			'inspector-panel--open': open,
		}">
		<div class="inspector-header">
			<h2>{{ t('journalnotes', 'Information') }}</h2>

			<NcButton
				type="tertiary"
				class="responsive-panel-close"
				:aria-label="t(
					'journalnotes',
					'Close note information',
				)"
				@click="$emit('close')">
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

		<InspectorTitle
			:model-value="noteTitle"
			:disabled="!hasContent"
			@update:model-value="$emit(
				'update:noteTitle',
				$event,
			)"
			@change="$emit('save-metadata')" />

		<div class="inspector-subheading">
			<h3>{{ t('journalnotes', 'Organization') }}</h3>
		</div>

		<InspectorCategories
			:categories="categories"
			:input-value="categoryInput"
			:suggestions="categorySuggestions"
			:disabled="!hasContent"
			@update:input-value="$emit(
				'update:categoryInput',
				$event,
			)"
			@add="$emit('add-category')"
			@remove="$emit('remove-category', $event)" />

		<InspectorTags
			:selected-tags="selectedTags"
			:input-value="tagInput"
			:show-suggestions="showTagSuggestions"
			:status="systemTagStatus"
			:available-tags="availableTags"
			:exact-tag-exists="exactTagExists"
			:normalized-input="normalizedTagInput"
			:disabled="!hasContent"
			@update:input-value="$emit(
				'update:tagInput',
				$event,
			)"
			@update:show-suggestions="$emit(
				'update:showTagSuggestions',
				$event,
			)"
			@select-or-create="$emit('select-or-create-tag')"
			@add-existing="$emit('add-existing-tag', $event)"
			@create="$emit('create-tag')"
			@remove="$emit('remove-tag', $event)" />

		<div class="inspector-subheading">
			<h3>{{ t('journalnotes', 'Connections') }}</h3>
		</div>

		<InspectorRelations
			:status="relationsStatus"
			:outgoing="outgoingRelations"
			:incoming="incomingRelations"
			:format-date="formatDate"
			@open-outgoing="$emit(
				'open-outgoing-relation',
				$event,
			)"
			@open-incoming="$emit(
				'open-incoming-relation',
				$event,
			)" />
	</aside>
</template>

<script>
import { NcButton } from '@nextcloud/vue'

import InspectorCategories from './InspectorCategories'
import InspectorRelations from './InspectorRelations'
import InspectorTags from './InspectorTags'
import InspectorTitle from './InspectorTitle'

export default {
	name: 'EntryInspector',

	components: {
		InspectorCategories,
		InspectorRelations,
		InspectorTags,
		InspectorTitle,
		NcButton,
	},

	props: {
		hasContent: {
			type: Boolean,
			default: false,
		},

		open: {
			type: Boolean,
			default: false,
		},

		metadataStatus: {
			type: String,
			default: null,
		},

		noteTitle: {
			type: String,
			default: '',
		},

		categories: {
			type: Array,
			default: () => [],
		},

		categoryInput: {
			type: String,
			default: '',
		},

		categorySuggestions: {
			type: Array,
			default: () => [],
		},

		selectedTags: {
			type: Array,
			default: () => [],
		},

		tagInput: {
			type: String,
			default: '',
		},

		showTagSuggestions: {
			type: Boolean,
			default: false,
		},

		systemTagStatus: {
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

		normalizedTagInput: {
			type: String,
			default: '',
		},

		relationsStatus: {
			type: String,
			default: null,
		},

		outgoingRelations: {
			type: Array,
			default: () => [],
		},

		incomingRelations: {
			type: Array,
			default: () => [],
		},

		formatDate: {
			type: Function,
			required: true,
		},
	},

	emits: [
		'close',
		'update:noteTitle',
		'update:categoryInput',
		'update:tagInput',
		'update:showTagSuggestions',
		'save-metadata',
		'add-category',
		'remove-category',
		'select-or-create-tag',
		'add-existing-tag',
		'create-tag',
		'remove-tag',
		'open-outgoing-relation',
		'open-incoming-relation',
	],
}
</script>

<style lang="scss">
@import './inspector';
</style>
