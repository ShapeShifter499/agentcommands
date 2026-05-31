<?php

declare(strict_types=1);

namespace OCA\AgentCommands\Controller;

use OCA\AgentCommands\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class ManifestController extends Controller {
	public function __construct(
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function commands(): JSONResponse {
		return new JSONResponse([
			'agents' => array_values(array_merge([$this->defaultOpenClawManifest()], $this->registeredManifests())),
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function upsertAgent(string $agentId): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->error('Authentication required.', Http::STATUS_UNAUTHORIZED);
		}

		$agentId = trim($agentId);
		if (!$this->isValidId($agentId)) {
			return $this->error('Agent id must contain only letters, numbers, underscores, and hyphens.', Http::STATUS_BAD_REQUEST);
		}

		$params = $this->request->getParams();
		$name = trim((string)($params['name'] ?? $agentId));
		$commands = $params['commands'] ?? null;
		if (!is_array($commands)) {
			return $this->error('Manifest must include a commands array.', Http::STATUS_BAD_REQUEST);
		}

		$manifest = [
			'id' => $agentId,
			'name' => $name !== '' ? $name : $agentId,
			'owner' => $user->getUID(),
			'updatedAt' => time(),
			'commands' => $this->normalizeCommands($commands),
		];
		if (count($manifest['commands']) === 0) {
			return $this->error('Manifest must include at least one valid command.', Http::STATUS_BAD_REQUEST);
		}

		$key = $this->manifestKey($user->getUID(), $agentId);
		$this->config->setAppValue(Application::APP_ID, $key, json_encode($manifest, JSON_THROW_ON_ERROR));

		return new JSONResponse(['agent' => $manifest], Http::STATUS_CREATED);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function deleteAgent(string $agentId): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return $this->error('Authentication required.', Http::STATUS_UNAUTHORIZED);
		}

		if (!$this->isValidId($agentId)) {
			return $this->error('Agent id must contain only letters, numbers, underscores, and hyphens.', Http::STATUS_BAD_REQUEST);
		}

		$this->config->deleteAppValue(Application::APP_ID, $this->manifestKey($user->getUID(), $agentId));

		return new JSONResponse(['deleted' => true]);
	}

	private function defaultOpenClawManifest(): array {
		return [
			'id' => 'openclaw',
			'name' => 'OpenClaw',
			'owner' => 'local',
			'commands' => [
				[
					'id' => 'help',
					'label' => 'Help',
					'description' => 'Show available commands.',
					'insert' => '/help',
				],
				[
					'id' => 'commands',
					'label' => 'Commands',
					'description' => 'List all OpenClaw commands.',
					'insert' => '/commands',
				],
				[
					'id' => 'status',
					'label' => 'Status',
					'description' => 'Show current OpenClaw status.',
					'insert' => '/status',
				],
				[
					'id' => 'btw',
					'label' => 'Side question',
					'description' => 'Ask a side question without changing future context.',
					'insert' => '/btw ',
				],
				[
					'id' => 'approve',
					'label' => 'Approve once',
					'description' => 'Approve a pending OpenClaw request once.',
					'insert' => '/approve ',
				],
				[
					'id' => 'deny',
					'label' => 'Deny',
					'description' => 'Deny a pending OpenClaw request.',
					'insert' => '/deny ',
				],
			],
		];
	}

	private function registeredManifests(): array {
		$manifests = [];
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

			if (is_array($manifest) && isset($manifest['id'], $manifest['name'], $manifest['commands']) && is_array($manifest['commands'])) {
				$manifests[] = $manifest;
			}
		}

		usort($manifests, static fn (array $a, array $b): int => strcasecmp((string)$a['name'], (string)$b['name']));
		return $manifests;
	}

	private function normalizeCommands(array $commands): array {
		$normalized = [];
		foreach ($commands as $command) {
			if (!is_array($command)) {
				continue;
			}

			$id = trim((string)($command['id'] ?? ''));
			$insert = (string)($command['insert'] ?? '');
			if (!$this->isValidId($id) || trim($insert) === '') {
				continue;
			}

			$normalized[] = [
				'id' => $id,
				'label' => substr(trim((string)($command['label'] ?? $id)), 0, 80),
				'description' => substr(trim((string)($command['description'] ?? '')), 0, 240),
				'insert' => substr($insert, 0, 1000),
			];
		}

		return array_slice($normalized, 0, 100);
	}

	private function isValidId(string $id): bool {
		return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) === 1;
	}

	private function manifestKey(string $userId, string $agentId): string {
		return 'agent:' . rawurlencode($userId) . ':' . rawurlencode($agentId);
	}

	private function error(string $message, int $status): JSONResponse {
		return new JSONResponse(['error' => $message], $status);
	}
}
