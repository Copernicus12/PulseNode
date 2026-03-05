#!/usr/bin/env python3
"""Simulate ESP32 socket telemetry and optionally send it to Laravel ingest endpoint."""

from __future__ import annotations

import argparse
import json
import math
import random
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

DEFAULT_ENV_FILE = "main_templates/my-app/.env"
DEFAULT_ENDPOINT = "http://127.0.0.1:8000/api/ingest"


@dataclass
class SocketProfile:
    name: str
    base_current: float
    jitter: float
    spike_probability: float
    spike_current: float


PROFILES: dict[int, SocketProfile] = {
    1: SocketProfile("Workstation", 0.42, 0.14, 0.16, 0.55),
    2: SocketProfile("Display", 0.25, 0.09, 0.08, 0.20),
    3: SocketProfile("Appliance", 0.55, 0.22, 0.14, 0.95),
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Simulate 3-socket telemetry and send payloads to /api/ingest."
    )
    parser.add_argument("--endpoint", default=DEFAULT_ENDPOINT, help="Ingest endpoint URL")
    parser.add_argument("--interval", type=float, default=1.0, help="Seconds between payloads")
    parser.add_argument(
        "--samples",
        type=int,
        default=0,
        help="Number of payloads to generate (0 = infinite)",
    )
    parser.add_argument("--voltage", type=float, default=230.0, help="Nominal voltage")
    parser.add_argument(
        "--toggle-prob",
        type=float,
        default=0.12,
        help="Per-socket probability of relay state toggle each cycle",
    )
    parser.add_argument(
        "--activity",
        choices=["quiet", "normal", "high"],
        default="high",
        help="Traffic intensity preset for current/power dynamics",
    )
    parser.add_argument(
        "--start-energy",
        type=float,
        default=0.75,
        help="Initial accumulated energy (kWh) used in generated payloads",
    )
    parser.add_argument(
        "--min-active-relays",
        type=int,
        default=1,
        help="Minimum number of relays forced active each cycle (0-3)",
    )
    parser.add_argument("--seed", type=int, default=None, help="Random seed")
    parser.add_argument("--token", default=None, help="ESP32 ingest token (optional)")
    parser.add_argument("--env-file", default=DEFAULT_ENV_FILE, help=".env file for token fallback")
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Generate payloads without sending HTTP requests",
    )
    return parser.parse_args()


def read_env_value(env_path: Path, key: str) -> str | None:
    if not env_path.exists():
        return None

    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        k, v = line.split("=", 1)
        if k.strip() != key:
            continue

        value = v.strip().strip('"').strip("'")
        return value or None

    return None


def clamp(value: float, lower: float, upper: float) -> float:
    return max(lower, min(upper, value))


def activity_multiplier(mode: str) -> float:
    if mode == "quiet":
        return 0.55
    if mode == "normal":
        return 0.85
    return 1.25


def sample_current(profile: SocketProfile, relay_on: bool, wave_factor: float, activity_factor: float) -> float:
    if not relay_on:
        return 0.0

    current = random.uniform(
        max(0.0, profile.base_current - profile.jitter),
        profile.base_current + profile.jitter,
    )

    if random.random() < profile.spike_probability:
        current += profile.spike_current

    current *= wave_factor * activity_factor
    return round(max(0.0, current), 3)


def build_payload(
    voltage: float,
    relay_state: dict[int, bool],
    accumulated_energy_kwh: float,
    delta_seconds: float,
    wave_factor: float,
    activity_factor: float,
) -> tuple[dict[str, Any], float]:
    c1 = sample_current(PROFILES[1], relay_state[1], wave_factor, activity_factor)
    c2 = sample_current(PROFILES[2], relay_state[2], wave_factor, activity_factor)
    c3 = sample_current(PROFILES[3], relay_state[3], wave_factor, activity_factor)

    total_current = round(c1 + c2 + c3, 3)
    total_power = round(voltage * total_current, 1)

    delta_energy = (total_power * (delta_seconds / 3600.0)) / 1000.0
    new_energy = accumulated_energy_kwh + delta_energy

    payload = {
        "voltage": round(voltage, 1),
        "current": total_current,
        "current_1": c1,
        "current_2": c2,
        "current_3": c3,
        "power": total_power,
        "energy": round(new_energy, 5),
        "relay_1": relay_state[1],
        "relay_2": relay_state[2],
        "relay_3": relay_state[3],
    }

    return payload, new_energy


def send_payload(endpoint: str, payload: dict[str, Any], token: str | None) -> tuple[int, str]:
    body = json.dumps(payload).encode("utf-8")
    headers = {"Content-Type": "application/json", "Accept": "application/json"}

    if token:
        headers["X-ESP32-TOKEN"] = token

    request = Request(endpoint, data=body, headers=headers, method="POST")

    try:
        with urlopen(request, timeout=6) as response:
            status = response.getcode()
            response_body = response.read().decode("utf-8", errors="replace")
            return status, response_body
    except HTTPError as exc:
        body_text = exc.read().decode("utf-8", errors="replace")
        return exc.code, body_text
    except URLError as exc:
        return 0, f"network_error: {exc.reason}"


def main() -> int:
    args = parse_args()

    if args.seed is not None:
        random.seed(args.seed)

    token = args.token
    if token is None:
        token = read_env_value(Path(args.env_file), "ESP32_INGEST_TOKEN")

    relay_state: dict[int, bool] = {1: True, 2: True, 3: False}
    interval = max(0.05, args.interval)
    min_active_relays = int(clamp(float(args.min_active_relays), 0, 3))
    activity_factor = activity_multiplier(args.activity)
    generated = 0
    energy_kwh = max(0.0, args.start_energy)

    print("Simulation started")
    print(f"endpoint={args.endpoint}")
    print(
        "interval="
        f"{interval}s, dry_run={args.dry_run}, samples={args.samples or 'infinite'}, "
        f"activity={args.activity}, start_energy={energy_kwh:.3f}kWh"
    )

    while args.samples == 0 or generated < args.samples:
        cycle_start = time.time()

        for socket_index in (1, 2, 3):
            if random.random() < args.toggle_prob:
                relay_state[socket_index] = not relay_state[socket_index]

        active_count = sum(1 for relay in relay_state.values() if relay)
        if active_count < min_active_relays:
            candidates = [socket for socket, relay in relay_state.items() if not relay]
            random.shuffle(candidates)
            for socket_index in candidates[: min_active_relays - active_count]:
                relay_state[socket_index] = True

        # Keep load visibly dynamic so dashboard cards change frequently.
        wave_base = 1.0 + 0.42 * math.sin((generated / 3.2) + random.uniform(-0.25, 0.25))
        wave_factor = clamp(wave_base, 0.45, 1.75)

        payload, energy_kwh = build_payload(
            args.voltage,
            relay_state,
            energy_kwh,
            interval,
            wave_factor,
            activity_factor,
        )

        ts = datetime.now(timezone.utc).isoformat()
        short = (
            f"{ts} | I={payload['current']:.3f}A "
            f"P={payload['power']:.1f}W "
            f"E={payload['energy']:.5f}kWh "
            f"R=[{int(payload['relay_1'])},{int(payload['relay_2'])},{int(payload['relay_3'])}] "
            f"wave={wave_factor:.2f}"
        )

        if args.dry_run:
            print(short)
            print(json.dumps(payload, ensure_ascii=True))
        else:
            status, response_text = send_payload(args.endpoint, payload, token)
            print(f"{short} | status={status}")
            if status >= 400 or status == 0:
                print(response_text)

        generated += 1

        elapsed = time.time() - cycle_start
        sleep_for = interval - elapsed
        if sleep_for > 0 and (args.samples == 0 or generated < args.samples):
            time.sleep(sleep_for)

    print("Simulation finished")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except KeyboardInterrupt:
        print("\nStopped by user")
        raise SystemExit(130)
