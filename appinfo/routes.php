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
		[
			'name' => 'personalSettings#setDefaultAgent',
			'url' => '/api/personal/default-agent',
			'verb' => 'PUT',
		],
		[
			'name' => 'adminSettings#setDefaultAgent',
			'url' => '/api/admin/default-agent',
			'verb' => 'PUT',
		],
		[
			'name' => 'adminSettings#setGroupDefault',
			'url' => '/api/admin/group-default',
			'verb' => 'PUT',
		],
	],
];
