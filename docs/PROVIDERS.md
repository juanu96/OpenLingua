# Translation provider integration

A provider is a separate integration that implements `OpenLingua\Contracts\Translation_Provider`.

```php
final class Example_Provider implements OpenLingua\Contracts\Translation_Provider {
    public function id() { return 'example'; }
    public function label() { return 'Example provider'; }
    public function is_configured() { return true; }

    public function translate(
        array $segments,
        $source_language,
        $target_language,
        array $context = array()
    ) {
        // Preserve HTML, blocks, shortcodes, placeholders, and protected terms.
        // Return WP_Error on transport, authentication, quota, or format errors.
        return $segments;
    }
}

OpenLingua\register_provider( new Example_Provider() );
```

The return value must contain `title` and `content`; `excerpt` is optional. OpenLingua validates the response, sanitizes content with WordPress APIs, records failures, and leaves machine-translated content in an editorial `in-progress` state.

Providers are responsible for credentials, consent notices, rate limits, cost reporting, retry policy, privacy documentation, and preservation of structured content. OpenLingua core contains no API keys and performs no outbound requests by itself.
