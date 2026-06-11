// Plain JS (no build step): saves the personal default-agent choice.
document.addEventListener('DOMContentLoaded', () => {
	const select = document.getElementById('agentcommands-default-agent')
	const status = document.getElementById('agentcommands-default-agent-status')
	if (!select) {
		return
	}

	select.addEventListener('change', async () => {
		status.textContent = '…'
		try {
			const response = await fetch(OC.generateUrl('/apps/agentcommands/api/personal/default-agent'), {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({ agent: select.value }),
			})
			status.textContent = response.ok ? t('agentcommands', 'Saved') : t('agentcommands', 'Could not save')
		} catch (error) {
			status.textContent = t('agentcommands', 'Could not save')
		}
		setTimeout(() => { status.textContent = '' }, 3000)
	})
})
