<?php

declare(strict_types=1);

namespace OCA\AgentCommands\Listener;

use OCA\AgentCommands\AppInfo\Application;
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
class TalkSlashCommandBridgeListener implements IEventListener {
	private const BOT_STATE_ENABLED = 1;
	private const BOT_FEATURE_WEBHOOK = 1;

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
		if (!class_exists(\OCA\Talk\Events\AMessageSentEvent::class)
			|| !$event instanceof \OCA\Talk\Events\AMessageSentEvent) {
			return;
		}

		if ($this->config->getAppValue(Application::APP_ID, 'talk_slash_bridge_enabled', '1') !== '1') {
			return;
		}

		$comment = $event->getComment();
		$rawMessage = trim($comment->getMessage());
		if ($rawMessage === '' || !preg_match('/^\/(?P<target>agent|nymble|aurel)(?:@[^\s]+)?(?:\s+[\s\S]*)?$/i', $rawMessage, $matches)) {
			return;
		}

		$participant = $event->getParticipant();
		$attendee = $participant?->getAttendee();
		if ($attendee === null) {
			return;
		}

		$actorType = $attendee->getActorType();
		if (!in_array($actorType, ['users', 'guests', 'federated_users', 'emails'], true)) {
			return;
		}

		$target = strtolower($matches['target']);
		$room = $event->getRoom();
		$this->logger->warning('Agent Commands slash bridge matched Talk command', [
			'app' => Application::APP_ID,
			'target' => $target,
			'roomToken' => $room->getToken(),
			'messageId' => (string)$comment->getId(),
		]);

		$bot = $this->findBotForTarget($room->getToken(), $target);
		if ($bot === null) {
			$this->logger->warning('Agent Commands slash bridge found no matching Talk bot', [
				'app' => Application::APP_ID,
				'target' => $target,
				'roomToken' => $room->getToken(),
			]);
			return;
		}

		$content = json_encode([
			'message' => $rawMessage,
			'parameters' => [],
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

		$body = json_encode([
			'type' => 'Create',
			'actor' => [
				'type' => 'Person',
				'id' => $actorType . '/' . $attendee->getActorId(),
				'name' => $attendee->getDisplayName(),
				'talkParticipantType' => (string)$attendee->getParticipantType(),
			],
			'object' => [
				'type' => 'Note',
				'id' => (string)$comment->getId(),
				'name' => 'message',
				'content' => $content,
				'mediaType' => 'text/markdown',
			],
			'target' => [
				'type' => 'Collection',
				'id' => $room->getToken(),
				'name' => $room->getName(),
			],
		], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

		$this->sendWebhook($bot, $body);
	}

	/**
	 * @return null|array{name: string, url: string, secret: string}
	 */
	private function findBotForTarget(string $roomToken, string $target): ?array {
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
			if ($url === '' || $secret === '' || ($features & self::BOT_FEATURE_WEBHOOK) === 0) {
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

	/**
	 * @param array{name: string, url: string, secret: string} $bot
	 */
	private function sendWebhook(array $bot, string $body): void {
		$random = $this->secureRandom->generate(64);
		$signature = hash_hmac('sha256', $random . $body, $bot['secret']);
		$backend = rtrim($this->config->getSystemValueString('overwrite.cli.url'), '/') . '/';

		$client = $this->clientService->newClient();
		$options = [
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
		];

		$this->logger->warning('Agent Commands slash bridge posting Talk bot webhook', [
			'app' => Application::APP_ID,
			'botName' => $bot['name'],
			'headerStyle' => 'X-Nextcloud-Talk',
			'payload' => $body,
		]);

		try {
			$response = $client->post($bot['url'], $options);
			$this->logger->warning('Agent Commands slash bridge invoked Talk bot webhook', [
				'app' => Application::APP_ID,
				'botName' => $bot['name'],
				'statusCode' => $response->getStatusCode(),
			]);
		} catch (\Throwable $error) {
			$this->logger->warning('Agent Commands slash bridge failed to invoke Talk bot ' . $bot['name'], [
				'app' => Application::APP_ID,
				'exception' => $error,
			]);
		}
	}
}
