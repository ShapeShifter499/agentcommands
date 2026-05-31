<?php

declare(strict_types=1);

namespace OCA\AgentCommands\AppInfo;

use OCA\AgentCommands\Listener\ReferenceRenderListener;
use OCA\AgentCommands\Reference\AgentCommandsProvider;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\Collaboration\Reference\RenderReferenceEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'agentcommands';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerReferenceProvider(AgentCommandsProvider::class);
		$context->registerEventListener(RenderReferenceEvent::class, ReferenceRenderListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, ReferenceRenderListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
