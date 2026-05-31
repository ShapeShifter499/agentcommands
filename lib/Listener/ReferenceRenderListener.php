<?php

declare(strict_types=1);

namespace OCA\AgentCommands\Listener;

use OCA\AgentCommands\AppInfo\Application;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<RenderReferenceEvent>
 */
class ReferenceRenderListener implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof RenderReferenceEvent) {
			return;
		}

		Util::addScript(Application::APP_ID, 'agentcommands.mjs');
		Util::addStyle(Application::APP_ID, 'agentcommands');
	}
}
