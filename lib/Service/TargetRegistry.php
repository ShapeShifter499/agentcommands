<?php

declare(strict_types=1);

namespace OCA\AgentCommands\Service;

use OCA\AgentCommands\AppInfo\Application;
use OCP\IConfig;

/**
 * Derives valid slash-command targets from the registered agent manifests
 * instead of a hardcoded agent list, so onboarding a new agent only requires
 * publishing a manifest (no app code change).
 */
class TargetRegistry {
	private const GENERIC_TARGET = 'agent';
	private const DEFAULT_AGENT_CONFIG_KEY = 'default_agent_target';

	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * Lowercase target tokens accepted after a leading slash, including the
	 * generic "agent" alias.
	 *
	 * @return string[]
	 */
	public function targets(): array {
		$targets = [self::GENERIC_TARGET];
		foreach ($this->registeredAgentIds() as $agentId) {
			$targets[] = $agentId;
		}

		return array_values(array_unique($targets));
	}

	/**
	 * Builds the message-match regex for the current targets, equivalent in
	 * shape to the previous hardcoded pattern.
	 */
	public function messagePattern(): ?string {
		$targets = $this->targets();
		if (count($targets) === 1 && $this->registeredAgentIds() === []) {
			// Only the generic alias exists but it has nothing to resolve to;
			// without manifests there is nothing to bridge.
			$defaultTarget = $this->defaultAgentTarget();
			if ($defaultTarget === '') {
				return null;
			}
			$targets[] = $defaultTarget;
		}

		$quoted = array_map(static fn (string $target): string => preg_quote($target, '/'), $targets);
		return '/^\/(?P<target>' . implode('|', $quoted) . ')(?:@[^\s]+)?(?:\s+[\s\S]*)?$/i';
	}

	/**
	 * Resolves the generic "agent" alias to the configured default agent.
	 */
	public function resolveAlias(string $target): string {
		$target = strtolower($target);
		if ($target !== self::GENERIC_TARGET) {
			return $target;
		}

		return $this->defaultAgentTarget();
	}

	/**
	 * @return string[] lowercase agent ids with a registered manifest
	 */
	public function registeredAgentIds(): array {
		$ids = [];
		foreach ($this->config->getAppKeys(Application::APP_ID) as $key) {
			if (!str_starts_with($key, 'agent:')) {
				continue;
			}
			$raw = $this->config->getAppValue(Application::APP_ID, $key, '');
			if ($raw === '') {
				continue;
			}

			try {
				$manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
			} catch (\JsonException) {
				continue;
			}

			$id = is_array($manifest) ? strtolower(trim((string)($manifest['id'] ?? ''))) : '';
			if ($id !== '' && preg_match('/^[a-z0-9_-]{1,64}$/', $id) === 1) {
				$ids[] = $id;
			}
		}

		sort($ids);
		return array_values(array_unique($ids));
	}

	private function defaultAgentTarget(): string {
		return strtolower(trim($this->config->getAppValue(
			Application::APP_ID,
			self::DEFAULT_AGENT_CONFIG_KEY,
			'nymble',
		)));
	}
}
