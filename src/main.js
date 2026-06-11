import axios from '@nextcloud/axios'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import {
  NcCustomPickerRenderResult,
  registerCustomPickerElement,
} from '@nextcloud/vue/functions/registerReference'

const PROVIDER_ID = 'agentcommands'

class AgentCommandsPicker extends HTMLElement {
  connectedCallback() {
    this.renderLoading()
    this.loadCommands()
  }

  async loadCommands() {
    try {
      const room = currentTalkRoomToken()
      const url = generateUrl('/apps/agentcommands/api/commands')
      const response = await axios.get(url, { params: room ? { room } : {} })
      this.renderCommands(response.data.agents ?? [], response.data.filteredByRoom ?? null)
    } catch (error) {
      this.renderError(error)
    }
  }

  renderLoading() {
    this.innerHTML = `<div class="agentcommands-picker">${escapeHtml(t('agentcommands', 'Loading commands...'))}</div>`
  }

  renderError() {
    this.innerHTML = `<div class="agentcommands-picker agentcommands-picker--error">${escapeHtml(t('agentcommands', 'Commands could not be loaded.'))}</div>`
  }

  renderCommands(agents, filteredByRoom) {
    if (agents.length === 0) {
      const message = filteredByRoom
        ? t('agentcommands', 'No agent bots are enabled in this conversation.')
        : t('agentcommands', 'No agent commands configured.')
      this.innerHTML = `<div class="agentcommands-picker">${escapeHtml(message)}</div>`
      return
    }

    const body = agents.flatMap((agent) => {
      const commands = agent.commands ?? []
      return [
        `<div class="agentcommands-picker__agent">${escapeHtml(agent.name ?? agent.id)}</div>`,
        ...commands.map((command) => this.renderCommand(command)),
      ]
    }).join('')

    this.innerHTML = `<div class="agentcommands-picker">${body}</div>`

    this.querySelectorAll('[data-agent-command]').forEach((button) => {
      button.addEventListener('click', () => {
        this.dispatchCommand(button.dataset.agentCommand ?? '')
      })
    })
  }

  renderCommand(command) {
    const insert = command.insert ?? ''
    return `
      <button type="button" class="agentcommands-picker__command" data-agent-command="${escapeAttribute(insert)}">
        <span class="agentcommands-picker__label">${escapeHtml(command.label ?? command.id ?? insert)}</span>
        <span class="agentcommands-picker__description">${escapeHtml(command.description ?? insert)}</span>
      </button>
    `
  }

  dispatchCommand(commandText) {
    this.dispatchEvent(new CustomEvent('submit', {
      bubbles: true,
      composed: true,
      detail: commandText,
    }))
  }
}

customElements.define('agentcommands-picker', AgentCommandsPicker)
registerCustomPickerElement(PROVIDER_ID, (el) => {
  const picker = document.createElement('agentcommands-picker')
  el.appendChild(picker)
  return new NcCustomPickerRenderResult(picker)
}, (el) => {
  el.replaceChildren()
}, 'normal')

function currentTalkRoomToken() {
  // Talk web UI routes look like /index.php/call/{token} or /call/{token}.
  const match = window.location.pathname.match(/\/call\/([A-Za-z0-9]{1,64})(?:\/|$)/)
  return match ? match[1] : null
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}

function escapeAttribute(value) {
  return escapeHtml(value).replaceAll('`', '&#096;')
}
