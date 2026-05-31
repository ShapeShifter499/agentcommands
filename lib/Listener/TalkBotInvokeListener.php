<?php

declare(strict_types=1);

namespace OCA\AgentCommands\Listener;

use OCA\AgentCommands\AppInfo\Application;
use OCA\Talk\Events\BotInvokeEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class TalkBotInvokeListener implements IEventListener {
	private const BOT_STATE_ENABLED = 1;
	private const BOT_FEATURE_WEBHOOK = 1;
	private const APP_BOT_URL = 'nextcloudapp://' . Application::APP_ID;

	public function __construct(
		private IDBConnection $db,
		private IClientService $clientService,
		private IConfig $config,
		private ICertificateManager $certificateManager,
		private ISecureRandom $secureRandom,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof BotInvokeEvent || $event->getBotUrl() !== self::APP_BOT_URL) {
			return;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'talk_event_bridge_enabled', '1') !== '1') {
			return;
		}

		$body = $event->getMessage();
		if (($body['type'] ?? '') !== 'Create') {
			return;
		}

		$content = json_decode((string)($body['object']['content'] ?? ''), true);
		if (!is_array($content)) {
			return;
		}

		$rawMessage = trim((string)($content['message'] ?? ''));
		if ($rawMessage === '' || !preg_match('/^\/(?P<target>agent|nymble|aurel)(?:@[^\s]+)?(?:\s+[\s\S]*)?$/i', $rawMessage, $matches)) {
			return;
		}

		$roomToken = (string)($body['target']['id'] ?? '');
		if ($roomToken === '') {
			return;
		}

		$target = strtolower($matches['target']);
		$this->logger->warning('Agent Commands event bot bridge matched Talk command', [
			'app' => Application::APP_ID,
			'target' => $target,
			'roomToken' => $roomToken,
		]);

		$bot = $this->findWebhookBotForTarget($roomToken, $target);
		if ($bot === null) {
			$this->logger->warning('Agent Commands event bot bridge found no matching webhook bot', [
				'app' => Application::APP_ID,
				'target' => $target,
				'roomToken' => $roomToken,
			]);
			return;
		}

		$this->sendWebhook($bot['name'], $bot['url'], $bot['secret'], json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
	}

	/**
	 * @return null|array{name: string, url: string, secret: string}
	 */
	private function findWebhookBotForTarget(string $roomToken, string $target): ?array {
		$query = $this->db->getQueryBuilder();
		$query->select('s.name', 's.url', 's.secret', 's.features')
			->from('talk_bots_server', 's')
			->innerJoin('s', 'talk_bots_conversation', 'c', $query->expr()->eq('s.id', 'c.bot_id'))
			->where($query->expr()->eq('c.token', $query->createNamedParameter($roomToken)))
			->andWhere($query->expr()->neq('s.state', $query->createNamedParameter(self::BOT_STATE_ENABLED - 1)))
			->andWhere($query->expr()->neq('c.state', $query->createNamedParameter(self::BOT_STATE_ENABLED - 1)));

		$result = $query->executeQuery();
		while ($row = $result->fetch()) {
			$name = (string)($row['name'] ?? '');
			$url = (string)($row['url'] ?? '');
			$secret = (string)($row['secret'] ?? '');
			$features = (int)($row['features'] ?? 0);
			if ($url === '' || $secret === '' || str_starts_with($url, 'nextcloudapp://') || ($features & self::BOT_FEATURE_WEBHOOK) === 0) {
				continue;
			}
			if ($this->botMatchesTarget($name, $target)) {
				return [
					'name' => $name,
					'url' => $url,
					'secret' => $secret,
				];
			}
		}

		return null;
	}

	private function botMatchesTarget(string $name, string $target): bool {
		$normalized = strtolower(trim($name));
		$firstWord = strtok($normalized, " \t\r\n") ?: '';
		if ($target === 'agent') {
			return $normalized === 'nymble' || $firstWord === 'nymble';
		}

		return $normalized === $target || $firstWord === $target;
	}

	private function sendWebhook(string $botName, string $botUrl, string $botSecret, string $body): void {
		$random = $this->secureRandom->generate(64);
		$signature = hash_hmac('sha256', $random . $body, $botSecret);
		$backend = rtrim($this->config->getSystemValueString('overwrite.cli.url'), '/') . '/';

		$client = $this->clientService->newClient();
		$promise = $client->postAsync($botUrl, [
			'verify' => $this->certificateManager->getAbsoluteBundlePath(),
			'nextcloud' => [
				'allow_local_address' => true,
			],
			'headers' => [
				'Content-Type' => 'application/json',
				'X-Nextcloud-Talk-Random' => $random,
				'X-Nextcloud-Talk-Signature' => $signature,
				'X-Nextcloud-Talk-Backend' => $backend,
				'OCS-APIRequest' => 'true',
			],
			'timeout' => 5,
			'body' => $body,
		]);

		$promise->then(function () use ($botName): void {
			$this->logger->warning('Agent Commands event bot bridge invoked Talk bot webhook', [
				'app' => Application::APP_ID,
				'botName' => $botName,
			]);
		}, function (\Throwable $error) use ($botName): void {
			$this->logger->warning('Agent Commands event bot bridge failed to invoke Talk bot ' . $botName, [
				'app' => Application::APP_ID,
				'botName' => $botName,
				'exceptionClass' => $error::class,
				'exceptionCode' => $error->getCode(),
				'exceptionMessage' => $error->getMessage(),
			]);
		});
	}
}
