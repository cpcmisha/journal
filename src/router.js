import { createRouter, createWebHistory } from 'vue-router'
import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import Journal from './Journal.vue'

const basePath = generateUrl('apps/journalnotes')

export default createRouter({
	history: createWebHistory(basePath),
	routes: [
		{
			path: '/date/:date',
			name: 'date',
			props: true,
			component: Journal,
		},
		{
			path: '/',
			redirect: () => ({
				name: 'date',
				params: {
					date: moment().format('YYYY-MM-DD'),
				},
			}),
		},
	],
})
