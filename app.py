from flask import Flask, render_template, jsonify
import paho.mqtt.client as mqtt
import paho.mqtt.publish as publish
import json
import threading
import time
import os
from datetime import datetime, timezone
from pymongo.mongo_client import MongoClient
from pymongo.server_api import ServerApi

app = Flask(__name__)

MQTT_BROKER = "broker.hivemq.com"
MQTT_TOPIC_DATA = "razvy_esp32_2026/data"
MQTT_TOPIC_CMD  = "razvy_esp32_2026/cmd"
MONGO_URI = os.getenv("MONGO_URI") or os.getenv("MONGODB_URI")
MONGO_DB_NAME = os.getenv("MONGO_DB_NAME") or os.getenv("MONGODB_DATABASE", "espData")
MONGO_COLLECTION = os.getenv("MONGO_COLLECTION") or os.getenv("MONGODB_COLLECTION", "readings")

mongo_client = None
mongo_collection = None


def init_mongo():
    global mongo_client, mongo_collection
    if not MONGO_URI:
        raise RuntimeError("Mongo URI missing. Set MONGO_URI or MONGODB_URI in environment.")
    mongo_client = MongoClient(MONGO_URI, server_api=ServerApi("1"))
    mongo_collection = mongo_client[MONGO_DB_NAME][MONGO_COLLECTION]


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
        try:
            mongo_collection.insert_one(document)
        except Exception:
            # Recreate Mongo client on transient failures and retry once.
            init_mongo()
            mongo_collection.insert_one(document)
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
    c = mqtt.Client(protocol=mqtt.MQTTv311)
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
    try:
        mongo_client.admin.command("ping")
        print("MongoDB connection is ready.")
    except Exception as e:
        print(f"MongoDB connection failed: {e}")

    app.run(host="0.0.0.0", port=5001)
