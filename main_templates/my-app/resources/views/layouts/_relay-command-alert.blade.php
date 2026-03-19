<div id="relay-command-toast-root" data-initial-guard='@json($relayCommandGuard)'></div>

<script>
(function () {
    var initialGuard = @json($relayCommandGuard);

    function normalizeGuard(raw) {
        raw = raw && typeof raw === 'object' ? raw : {};
        var maxAgeSeconds = Number(raw.max_age_seconds || 90);

        return {
            can_turn_on: Boolean(raw.can_turn_on),
            reason: typeof raw.reason === 'string' ? raw.reason : null,
            message: typeof raw.message === 'string' ? raw.message : null,
            age_seconds: Number.isFinite(Number(raw.age_seconds)) ? Number(raw.age_seconds) : null,
            max_age_seconds: Number.isFinite(maxAgeSeconds) ? maxAgeSeconds : 90,
            last_seen_at: typeof raw.last_seen_at === 'string' ? raw.last_seen_at : null,
        };
    }

    function formatBlockedMessage(ageSeconds, maxAgeSeconds, hasSeenTelemetry) {
        if (!hasSeenTelemetry) {
            return 'Socket power-on is unavailable because the power strip has not sent telemetry yet. It may be unplugged or not connected.';
        }

        return 'Socket power-on is blocked because the latest ESP32 reading is older than ' + maxAgeSeconds + 's. The strip may be unplugged or it may have lost the connection.';
    }

    function deriveGuardFromLatest(latest) {
        latest = latest && typeof latest === 'object' ? latest : {};
        var currentGuard = normalizeGuard(window.__pulsenodeRelayCommandGuard || initialGuard);
        var maxAgeSeconds = currentGuard.max_age_seconds;
        var updatedAt = typeof latest.updated_at === 'string' ? latest.updated_at : currentGuard.last_seen_at;

        if (!updatedAt) {
            return normalizeGuard({
                can_turn_on: false,
                reason: 'never_seen',
                message: formatBlockedMessage(null, maxAgeSeconds, false),
                age_seconds: null,
                max_age_seconds: maxAgeSeconds,
                last_seen_at: null,
            });
        }

        var timestamp = Date.parse(updatedAt);
        if (!Number.isFinite(timestamp)) {
            return normalizeGuard({
                can_turn_on: false,
                reason: 'never_seen',
                message: formatBlockedMessage(null, maxAgeSeconds, false),
                age_seconds: null,
                max_age_seconds: maxAgeSeconds,
                last_seen_at: null,
            });
        }

        var ageSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));
        if (ageSeconds > maxAgeSeconds) {
            return normalizeGuard({
                can_turn_on: false,
                reason: 'stale',
                message: formatBlockedMessage(ageSeconds, maxAgeSeconds, true),
                age_seconds: ageSeconds,
                max_age_seconds: maxAgeSeconds,
                last_seen_at: updatedAt,
            });
        }

        return normalizeGuard({
            can_turn_on: true,
            reason: null,
            message: null,
            age_seconds: ageSeconds,
            max_age_seconds: maxAgeSeconds,
            last_seen_at: updatedAt,
        });
    }

    function setGuard(nextGuard, shouldBroadcast) {
        var normalized = normalizeGuard(nextGuard);
        window.__pulsenodeRelayCommandGuard = normalized;

        if (shouldBroadcast !== false) {
            window.dispatchEvent(new CustomEvent('pulsenode:relay-guard', { detail: normalized }));
        }

        return normalized;
    }

    function showNotification(message, guard) {
        var normalized = guard ? normalizeGuard(guard) : normalizeGuard(window.__pulsenodeRelayCommandGuard || initialGuard);

        window.dispatchEvent(new CustomEvent('pulsenode:relay-guard-notification', {
            detail: {
                message: typeof message === 'string' && message ? message : normalized.message,
                guard: normalized,
            },
        }));
    }

    window.pulsenodeSetRelayCommandGuard = function (guard) {
        return setGuard(guard, true);
    };

    window.pulsenodeShowRelayCommandNotification = showNotification;

    window.pulsenodeRefreshRelayCommandGuard = function (latest) {
        return setGuard(deriveGuardFromLatest(latest || window.__pulsenodeLatest || {}), true);
    };

    window.pulsenodeEnsureRelayCommandAllowed = function (turnOn) {
        if (!turnOn) {
            return true;
        }

        var nextGuard = window.pulsenodeRefreshRelayCommandGuard();
        if (!nextGuard.can_turn_on) {
            showNotification(nextGuard.message, nextGuard);
        }
        return nextGuard.can_turn_on;
    };

    setGuard(initialGuard, false);

    window.addEventListener('pulsenode:latest', function (event) {
        window.pulsenodeRefreshRelayCommandGuard(event.detail || {});
    });

    window.setInterval(function () {
        window.pulsenodeRefreshRelayCommandGuard();
    }, 15000);
})();
</script>
