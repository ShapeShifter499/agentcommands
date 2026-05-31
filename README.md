# Agent Commands

Agent Commands is a Nextcloud Smart Picker app for AI-agent bot commands.

The goal is to give Talk users a native `/` picker entry named **Agent commands**. The picker can list commands from OpenClaw first, and later from any agent service that publishes a simple manifest.

## Current Status

This is an early scaffold:

- registers a discoverable reference provider: `agentcommands`
- loads a custom Smart Picker element
- exposes a local command manifest endpoint at `/apps/agentcommands/api/commands`
- lets authenticated Nextcloud accounts publish command manifests
- inserts Talk-ready command text such as `/nymble status`
- experimentally bridges Talk messages like `/nymble status` to the matching configured Talk bot webhook
- can also follow Nextcloud's in-process bot pattern with a `nextcloudapp://agentcommands` event bot, similar to `nextcloud/command_bot`

The default command menu is designed for the live slash bridge: it inserts `/nymble help`, `/nymble commands`, `/nymble status`, `/nymble btw `, `/nymble `, `/approve `, and `/deny `.
The slash bridge handles the Talk behavior where slash-style messages can be stored as normal messages without waking configured bot webhooks: when Talk stores `/nymble ...`, `/agent ...`, or `/aurel ...`, the app signs and forwards a standard Talk bot webhook payload to the matching bot configured in that conversation.

### Experimental Talk event bot bridge

Nextcloud's `command_bot` app uses a local Talk event bot instead of the deprecated `talk_commands` table. To try the same path, install Agent Commands as a Talk event bot, set it up in the room, and keep the existing webhook bot such as `Nymble` configured in that same room:

```bash
SECRET="$(openssl rand -hex 64)"
php occ talk:bot:install --feature event \
  "Agent Commands" "$SECRET" "nextcloudapp://agentcommands" \
  "Bridge /nymble-style Talk messages to configured agent webhook bots"

php occ talk:bot:list --output=json_pretty
php occ talk:bot:setup <agent-commands-bot-id> <room-token>
```

When the event bot receives `/nymble ...`, `/agent ...`, or `/aurel ...`, Agent Commands looks for the matching webhook bot in that room and forwards the normal signed Talk bot payload to that bot's webhook URL.

### Talk bot administration notes

Configured webhook bots should usually enable all features that OpenClaw may use:

```bash
php occ talk:bot:state <bot-id> 1 \
  --feature webhook \
  --feature response \
  --feature reaction
```

Use `talk:bot:state` when only the enabled state or feature list needs to change. It avoids uninstalling and reinstalling a working bot registration.

After replacing the installed app checkout in a running Nextcloud container, run `php occ upgrade` and restart the Nextcloud container or PHP-FPM process before testing Talk events. Nextcloud can otherwise keep an old event-listener registration loaded through PHP/opcache even when the files and installed app version are already updated.

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
