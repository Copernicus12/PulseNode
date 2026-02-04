from flask import Flask, render_template, jsonify
import paho.mqtt.client as mqtt
import paho.mqtt.publish as publish
import json
import threading

app = Flask(__name__)

MQTT_BROKER = "192.168.1.246"
MQTT_TOPIC_DATA = "esp32/data"
MQTT_TOPIC_CMD  = "esp32/cmd"

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
        latest_data = json.loads(msg.payload.decode())
    except:
        pass

def mqtt_thread():
    c = mqtt.Client(protocol=mqtt.MQTTv311)
    c.on_message = on_message
    c.connect(MQTT_BROKER)
    c.subscribe(MQTT_TOPIC_DATA)
    c.loop_forever()

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
    app.run(host="0.0.0.0", port=5000)
