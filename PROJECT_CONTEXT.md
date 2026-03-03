# PulseNode – Context proiect licență

## Viziune generală
PulseNode este o soluție inteligentă pentru monitorizarea consumului de energie electrică, construită în jurul unui prelungitor electric inteligent cu **3 prize independente**.

Fiecare priză măsoară consumul individual al dispozitivului conectat, iar datele sunt transmise în timp real către o aplicație web pentru analiză și control.

## Arhitectură hardware
- **Microcontroler:** ESP32-S3 DevKit
- **Senzori de curent:** ACS712 (câte unul pe fiecare priză)
- **Număr prize monitorizate:** 3
- **Comunicare date:** MQTT

## Arhitectură software
- **Backend:** Laravel
- **Frontend:** Vue + componente ShadCN
- **Stocare:** bază de date (persistență pentru istoric)
- **Transport date în timp real:** MQTT (de la ESP32-S3 către server)

## Funcționalități principale aplicație web
1. Monitorizare în timp real a consumului pentru fiecare priză.
2. Vizualizare istoric de consum.
3. Statistici și grafice relevante.
4. Control stare socket-uri (pornit/oprit) pentru fiecare priză.

## Scopul proiectului
Crearea unei soluții accesibile și eficiente pentru monitorizarea și optimizarea consumului de energie electrică în mediul casnic sau de birou.

## Notă de continuitate (pentru modificări viitoare)
- Acest fișier este sursa de context pentru direcția proiectului.
- Orice schimbare majoră de arhitectură, funcționalitate sau scop trebuie adăugată aici.
- Actualizările de context trebuie păstrate atât în repository (acest fișier), cât și în istoricul cloud al proiectului prin commit + PR.
