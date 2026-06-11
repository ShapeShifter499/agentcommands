// Plain JS (no build step): saves admin server/group defaults for /agent.
document.addEventListener('DOMContentLoaded', () => {
	const status = document.getElementById('agentcommands-admin-status')

	async function save(url, body) {
		status.textContent = '…'
		try {
			const response = await fetch(OC.generateUrl(url), {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify(body),
			})
			status.textContent = response.ok ? t('agentcommands', 'Saved') : t('agentcommands', 'Could not save')
		} catch (error) {
			status.textContent = t('agentcommands', 'Could not save')
		}
		setTimeout(() => { status.textContent = '' }, 3000)
	}

	document.getElementById('agentcommands-admin-server-default')?.addEventListener('change', (event) => {
		save('/apps/agentcommands/api/admin/default-agent', { agent: event.target.value })
	})

	document.querySelectorAll('.agentcommands-admin-group').forEach((select) => {
		select.addEventListener('change', () => {
			save('/apps/agentcommands/api/admin/group-default', {
				group: select.dataset.group,
				agent: select.value,
			})
		})
	})
})
