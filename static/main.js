function refresh() {
  fetch("/api/latest")
    .then(r => r.json())
    .then(d => {
      document.getElementById("voltage").textContent = d.voltage + " V";
      document.getElementById("current").textContent = d.current + " A";
      document.getElementById("power").textContent   = d.power + " W";
      document.getElementById("energy").textContent  = d.energy + " kWh";
    });
}

function relay(state) {
  fetch("/api/relay/" + state)
    .then(r => r.json())
    .then(x => {
      document.getElementById("relayStatus").textContent = "Comandă trimisă: " + x.sent;
    });
}

setInterval(refresh, 1500);
refresh();
