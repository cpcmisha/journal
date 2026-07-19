<template>
	<NcAppNavigation class="dates-navigation">
		<div class="navigation-wrapper">
			<NcButton
				class="icon icon-view-previous"
				:aria-label="t('journalnotes', 'Previous day')"
				@click="goPreviousDay" />

			<input
				ref="nativeDatePicker"
				class="native-date-picker"
				type="date"
				:value="date"
				:max="today"
				@change="onNativeDateChange">

			<NcButton
				class="open-calendar"
				:aria-label="t(
					'journalnotes',
					'Open calendar: {date}',
					{ date: formattedDate },
				)"
				@click="openCalendar">
				{{ formattedDate }}
			</NcButton>

			<NcButton
				v-if="showNextDayButton"
				class="icon icon-view-next"
				:aria-label="t('journalnotes', 'Next day')"
				@click="goNextDay" />
		</div>

		<template #list>
			<ul>
				<NcListItem
					v-for="entry in entries"
					:key="entry.date"
					:name="entry.title || formatDate(entry.date)"
					:bold="false"
					:compact="true"
					counter-type="highlighted"
					@click="openEntry(entry.date)">
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
</template>

<script>
import moment from '@nextcloud/moment'
import {
	NcActionLink,
	NcAppNavigation,
	NcAppNavigationIconBullet,
	NcAppNavigationItem,
	NcButton,
	NcListItem,
} from '@nextcloud/vue'

import FilePdfBox from 'vue-material-design-icons/FilePdfBox.vue'
import Markdown from 'vue-material-design-icons/LanguageMarkdown.vue'

export default {
	name: 'DatesNavigation',

	components: {
		FilePdfBox,
		Markdown,
		NcActionLink,
		NcAppNavigation,
		NcAppNavigationIconBullet,
		NcAppNavigationItem,
		NcButton,
		NcListItem,
	},

	props: {
		date: {
			type: String,
			required: true,
		},

		entries: {
			type: Array,
			default: () => [],
		},

		pdfDownloadLink: {
			type: String,
			required: true,
		},

		markdownDownloadLink: {
			type: String,
			required: true,
		},
	},

	emits: [
		'change-date',
	],

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
	},

	methods: {
		changeDate(value) {
			const parsedDate = moment(value)

			if (!parsedDate.isValid()) {
				return
			}

			const targetDate = parsedDate.format('YYYY-MM-DD')

			if (targetDate === this.date) {
				return
			}

			this.$emit('change-date', targetDate)
		},

		openEntry(entryDate) {
			if (!this.isCurrentDate(entryDate)) {
				this.changeDate(entryDate)
			}
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
			const value = event.target?.value

			if (value) {
				this.changeDate(value)
			}
		},

		goPreviousDay() {
			this.changeDate(
				moment(this.date).subtract(1, 'day'),
			)
		},

		goNextDay() {
			this.changeDate(
				moment(this.date).add(1, 'day'),
			)
		},

		isCurrentDate(entryDate) {
			return this.date === entryDate
		},

		formatDate(entryDate) {
			return moment(entryDate).format('LL')
		},
	},
}
</script>

<style lang="scss" src="./navigation.scss"></style>
