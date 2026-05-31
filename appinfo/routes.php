<?php

declare(strict_types=1);

return [
	'routes' => [
		[
			'name' => 'manifest#commands',
			'url' => '/api/commands',
			'verb' => 'GET',
		],
		[
			'name' => 'manifest#upsertAgent',
			'url' => '/api/agents/{agentId}',
			'verb' => 'PUT',
		],
		[
			'name' => 'manifest#deleteAgent',
			'url' => '/api/agents/{agentId}',
			'verb' => 'DELETE',
		],
	],
];
