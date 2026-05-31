# Contributing

Thanks for helping improve Agent Commands.

## AI-Assisted Contributions

AI-assisted work is welcome when it is reviewed and owned by the human contributor.

For transparency, include an `Assisted-by:` trailer in commits or pull request descriptions when an AI coding tool materially helped produce the change. Use the real tool and model when known.

Example:

```text
Assisted-by: OpenClaw:openai/gpt-5.5
```

Do not add `Signed-off-by:` unless you personally intend to make the legal certification required by the target project.

## Quality Bar

- Keep the Smart Picker UI small and predictable.
- Do not store bot credentials or secrets in manifests.
- Prefer app-password-authenticated bot accounts for manifest publishing.
- Test against a real Nextcloud instance before marking Smart Picker behavior complete.
