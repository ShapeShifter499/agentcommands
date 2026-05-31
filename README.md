# Agent Commands

Agent Commands is a Nextcloud Smart Picker app for AI-agent bot commands.

The goal is to give Talk users a native `/` picker entry named **Agent commands**. The picker can list commands from OpenClaw first, and later from any agent service that publishes a simple manifest.

## Current Status

This is an early scaffold:

- registers a discoverable reference provider: `agentcommands`
- loads a custom Smart Picker element
- exposes a local command manifest endpoint at `/apps/agentcommands/api/commands`
- lets authenticated Nextcloud accounts publish command manifests
- inserts OpenClaw-oriented command text such as `commands`
- experimentally bridges Talk messages like `/nymble status` to the matching configured Talk bot webhook

The initial inserted command text avoids slash-style commands because Talk may store those messages without waking configured bot webhooks on the live server.
The slash bridge is a server-side workaround for that behavior: when Talk stores `/nymble ...` as a normal message, the app signs and forwards a standard Talk bot webhook payload to the matching bot configured in that conversation.

## Development

```bash
npm install
npm run build
```

For a local Nextcloud checkout, install the app directory as `apps/agentcommands`, then enable it:

```bash
php occ app:enable agentcommands
```

When replacing an already-installed checkout after an app version bump, run the upgrade step too:

```bash
php occ upgrade
```

## App Store Prep

Before publishing, replace the repository URLs in `appinfo/info.xml`, test against the target Nextcloud versions, add screenshots, add translations, and generate a signed release archive following Nextcloud app store requirements.

## Manifest Direction

The picker combines the built-in OpenClaw manifest with manifests published by authenticated Nextcloud accounts.

Bot accounts can publish or replace their command list with a normal app password:

```bash
curl -u 'bot-user:app-password' \
  -H 'OCS-APIRequest: true' \
  -H 'Content-Type: application/json' \
  -X PUT \
  'https://cloud.example.com/apps/agentcommands/api/agents/my-agent' \
  --data '{
    "name": "My Agent",
    "commands": [
      {
        "id": "help",
        "label": "Help",
        "description": "Show this agent command help.",
        "insert": "@My Agent !help"
      }
    ]
  }'
```

The app stores manifests under the publishing Nextcloud account, so a bot can update or delete its own list without admin rights:

```bash
curl -u 'bot-user:app-password' \
  -H 'OCS-APIRequest: true' \
  -X DELETE \
  'https://cloud.example.com/apps/agentcommands/api/agents/my-agent'
```

The manifest contract is intentionally small:

- `id`: stable command id, letters/numbers/underscore/hyphen only
- `label`: short picker label
- `description`: short picker description
- `insert`: text the picker inserts into the composer

Future versions can evolve this into one of these:

- admin-configured agent manifests
- a signed local JSON file
- a trusted service endpoint per agent
- a small app settings page for command registration
