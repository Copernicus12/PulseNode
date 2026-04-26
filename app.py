from flask import Flask, render_template, jsonify
import paho.mqtt.client as mqtt
import paho.mqtt.publish as publish
import json
import threading
import time
import os
import ssl
from datetime import datetime, timezone
from pymongo.mongo_client import MongoClient
from pymongo.server_api import ServerApi

app = Flask(__name__)


def load_local_env_files():
    # Lightweight .env loader to avoid external dependency for local runs.
    candidate_paths = [
        os.path.join(os.getcwd(), ".env"),
        os.path.join(os.getcwd(), "main_templates", "my-app", ".env"),
    ]

    for path in candidate_paths:
        if not os.path.exists(path):
            continue

        with open(path, "r", encoding="utf-8") as env_file:
            for raw_line in env_file:
                line = raw_line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue

                key, value = line.split("=", 1)
                key = key.strip()
                value = value.strip().strip('"').strip("'")

                if key and key not in os.environ:
                    os.environ[key] = value


load_local_env_files()

MQTT_BROKER = "broker.hivemq.com"
MQTT_TOPIC_DATA = "razvy_esp32_2026/data"
MQTT_TOPIC_CMD  = "razvy_esp32_2026/cmd"
MONGO_URI = os.getenv("MONGO_URI") or os.getenv("MONGODB_URI")
MONGO_DB_NAME = os.getenv("MONGO_DB_NAME") or os.getenv("MONGODB_DATABASE", "espData")
MONGO_COLLECTION = os.getenv("MONGO_COLLECTION") or os.getenv("MONGODB_COLLECTION", "readings")
MONGO_TLS_CA_FILE = os.getenv("MONGO_TLS_CA_FILE")
MONGO_RETRY_INTERVAL = int(os.getenv("MONGO_RETRY_INTERVAL", "30"))

mongo_client = None
mongo_collection = None
mongo_ready = False
mongo_lock = threading.Lock()
mongo_last_log = None


def build_mongo_kwargs():
    kwargs = {
        "server_api": ServerApi("1"),
        "serverSelectionTimeoutMS": 5000,
        "connectTimeoutMS": 5000,
        "socketTimeoutMS": 5000,
    }

    tls_options = {
        "tls": True,
    }

    if MONGO_TLS_CA_FILE:
        tls_options["tlsCAFile"] = MONGO_TLS_CA_FILE
    else:
        try:
            import certifi

            tls_options["tlsCAFile"] = certifi.where()
        except Exception:
            # Fall back to the platform trust store if certifi is unavailable.
            pass

    kwargs.update(tls_options)
    return kwargs


def log_mongo_once(message):
    global mongo_last_log
    if mongo_last_log != message:
        mongo_last_log = message
        print(message)


def init_mongo():
    global mongo_client, mongo_collection, mongo_ready
    if not MONGO_URI:
        mongo_ready = False
        mongo_client = None
        mongo_collection = None
        log_mongo_once("[mongo] persistence disabled; set MONGO_URI or MONGODB_URI to enable it.")
        return False

    try:
        mongo_client = MongoClient(MONGO_URI, **build_mongo_kwargs())
        mongo_collection = mongo_client[MONGO_DB_NAME][MONGO_COLLECTION]
        mongo_client.admin.command("ping")
        mongo_ready = True
        log_mongo_once("[mongo] connection is ready.")
        return True
    except Exception as e:
        mongo_ready = False
        mongo_client = None
        mongo_collection = None
        log_mongo_once(f"[mongo] connection failed: {e}")
        return False


init_mongo()

latest_data = {
    "voltage": 0,
    "current": 0,
    "power": 0,
    "energy": 0,
    "relay": False
}

def on_message(client, userdata, msg):
    global latest_data
    try:
        payload = json.loads(msg.payload.decode())
        if not isinstance(payload, dict):
            print("[on_message] ignored non-object payload")
            return

        latest_data = payload

        document = {
            "topic": msg.topic,
            "received_at": datetime.now(timezone.utc),
            "payload": payload,
        }
        if mongo_ready and mongo_collection is not None:
            try:
                mongo_collection.insert_one(document)
            except Exception as e:
                print(f"[mongo] insert failed: {e}")
                with mongo_lock:
                    init_mongo()
    except Exception as e:
        print(f"[on_message] failed to process message: {e}")


def on_connect(client, userdata, flags, reason_code, properties=None):
    if reason_code == 0:
        print("[mqtt] connected")
        client.subscribe(MQTT_TOPIC_DATA)
    else:
        print(f"[mqtt] connect failed, rc={reason_code}")


def on_disconnect(client, userdata, reason_code, properties=None):
    print(f"[mqtt] disconnected, rc={reason_code}")

def mqtt_thread():
    c = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2, protocol=mqtt.MQTTv311)
    c.on_message = on_message
    c.on_connect = on_connect
    c.on_disconnect = on_disconnect
    c.reconnect_delay_set(min_delay=1, max_delay=30)

    while True:
        try:
            c.connect(MQTT_BROKER, keepalive=60)
            c.loop_forever(retry_first_connection=True)
        except Exception as e:
            print(f"[mqtt_thread] connection loop failed: {e}; retrying in 3s")
            time.sleep(3)

threading.Thread(target=mqtt_thread, daemon=True).start()


def mongo_retry_thread():
    if not MONGO_URI:
        return

    while True:
        if not mongo_ready:
            with mongo_lock:
                init_mongo()
        time.sleep(MONGO_RETRY_INTERVAL)


threading.Thread(target=mongo_retry_thread, daemon=True).start()

@app.route("/")
def index():
    return render_template("index.html")

@app.route("/api/latest")
def api_latest():
    return jsonify(latest_data)

@app.route("/api/relay/<state>")
def api_relay(state):
    payload = "ON" if state == "on" else "OFF"
    publish.single(MQTT_TOPIC_CMD, payload, hostname=MQTT_BROKER)
    return jsonify({"status": "ok", "sent": payload})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001)
