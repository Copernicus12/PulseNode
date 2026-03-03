function refresh() {
  fetch("/api/latest")
    .then(r => r.json())
    .then(d => {
      document.getElementById("voltage").textContent = d.voltage + " V";
      document.getElementById("current").textContent = d.current + " A";
      document.getElementById("power").textContent   = d.power + " W";
      document.getElementById("energy").textContent  = d.energy + " kWh";

      const extraCurrents = ["current2", "current3"];
      extraCurrents.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = d.current + " A";
      });

      const relayState = d.relay ? "PORNIT" : "OPRIT";
      ["relayStatus-1", "relayStatus-2", "relayStatus-3"].forEach(id => {
        const chip = document.getElementById(id);
        if (chip) chip.textContent = relayState;
      });
    });
}

function relay(relayId, state) {
  fetch("/api/relay/" + state)
    .then(r => r.json())
    .then(x => {
      const chip = document.getElementById("relayStatus-" + relayId);
      if (chip) chip.textContent = "Trimis: " + x.sent;
    });
}

document.querySelectorAll("[data-relay]").forEach(card => {
  const relayId = card.getAttribute("data-relay");
  card.querySelectorAll(".relay-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const state = btn.getAttribute("data-state");
      relay(relayId, state);
    });
  });
});

setInterval(refresh, 1500);
refresh();
