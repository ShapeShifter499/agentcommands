<?php

declare(strict_types=1);

namespace OCA\AgentCommands\Reference;

use OCA\AgentCommands\AppInfo\Application;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\Reference;
use OCP\IURLGenerator;
use OCP\IL10N;

class AgentCommandsProvider extends ADiscoverableReferenceProvider {
	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l10n,
	) {
	}

	public function getId(): string {
		return 'agentcommands';
	}

	public function getTitle(): string {
		return $this->l10n->t('Agent commands');
	}

	public function getOrder(): int {
		return 45;
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app.svg');
	}

	public function matchReference(string $referenceText): bool {
		return str_starts_with($referenceText, 'agent-command://');
	}

	public function resolveReference(string $referenceText): ?IReference {
		if (!$this->matchReference($referenceText)) {
			return null;
		}

		$command = substr($referenceText, strlen('agent-command://'));
		$reference = new Reference($referenceText);
		$reference->setTitle($this->l10n->t('Agent command: %s', [$command]));
		$reference->setDescription($this->l10n->t('AI-agent command selected from Smart Picker'));
		$reference->setUrl($referenceText);
		$reference->setRichObject('agent-command', [
			'id' => $referenceText,
			'name' => $command,
			'description' => $this->l10n->t('AI-agent command'),
		]);

		return $reference;
	}

	public function getCachePrefix(string $referenceId): string {
		return 'agentcommands';
	}

	public function getCacheKey(string $referenceId): ?string {
		return null;
	}
}
