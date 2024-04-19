[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

## 2. Voraussetzungen

- IP-Symcon ab Version 6.0
- ein Produkt, das mit der VeSync-Cloud verbunden ist

Getestet wurde das Modul bisher mit:

| Hersteller | Bereich        | Modell |
| :--------- | :------------- | :----- |
| levoit     | Luftreiniger   | Vital 100S |
| levoit     | Luftreiniger   | Core 300S |

Es sollte bei den anderen Luftreinigern von levoit recht einfach sein, diese mit aufzunehmen, bei anderen Geräten, die in VeSync integriet sind, muss eine Anschlussmöglichkeit geprüft werden.
Bei Bedarf bitte an den Autor wenden.

Das Modul basiert unter anderem auf Informationen aus dem Projekt [pyvesync](https://pypi.org/project/pyvesync).

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *VeSync* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/VeSync.git` installiert werden.

### b. Einrichtung in IPS

## 4. Funktionsreferenz

alle Funktionen sind über _RequestAction_ der jew. Variablen ansteuerbar

## 5. Konfiguration

### VeSyncIO

#### Properties

| Eigenschaft            | Typ      | Standardwert | Beschreibung |
| :--------------------- | :------  | :----------- | :----------- |
| Instanz deaktivieren   | boolean  | false        | Instanz temporär deaktivieren |
|                        |          |              | |
| Zugangsdaten           |          |              | Benutzername und Passwort der VeSync-Cloud |

#### Actions

| Bezeichnung    | Beschreibung |
| :------------- | :----------- |
| Zugang testen  | Zugang zur VeSync-Cloud testen |

### VeSyncConfig

| Eigenschaft | Typ      | Standardwert | Beschreibung |
| :---------- | :------  | :----------- | :----------- |
| Kategorie   | integer  | 0            | Kategorie im Objektbaum, unter dem die Instanzen angelegt werden _[1]_ |

_[1]_: nur bis IPS-Version 7 vorhanden, danach ist eine Einstellmöglichkeit Bestandteil des Standard-Konfigurators

### VeSyncDevice

#### Properties

| Eigenschaft              | Typ      | Standardwert | Beschreibung |
| :----------------------- | :------  | :----------- | :----------- |
| Instanz deaktivieren     | boolean  | false        | Instanz temporär deaktivieren |
|                          |          |              | |
| Basis-Konfiguration      |          |              | Basis-Konfiguration, gesetzt durch den Konfigurator |
|                          |          |              | |
| Aktualisierungsintervall | integer  | 60           | Abrufintervall in Sekunden |

#### Aktionen

| Bezeichnung         | Beschreibung |
| :------------------ | :----------- |
| aktualisiere Status | Geräte-Status abfragen |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Boolean<br>
VeSync.OnOff

* Integer<br>
VeSync.AQLevel,
VeSync.AQValue,
VeSync.Percent,
VeSync.PM25,
VeSync.SpeedLevel123,
VeSync.SpeedLevel1234,
VeSync.Wifi,
VeSync.WorkMode0123,
VeSync.WorkMode01234

## 6. Anhang

### Quellen

https://github.com/webdjoe/pyvesync.git

### GUIDs
- Modul: `{20EF9EEF-5E6E-F702-AAFC-86E3A39AE1E2}`
- Instanzen:
  - VeSyncIO: `{0E57FA4B-B212-E8E7-715D-4F5BA30C7817}`
  - VeSyncConfig: `{845E5FD0-C11E-6405-472A-F3E20EBC2A46}`
  - VeSyncDevice: `{BA4E3595-F713-49CD-D25D-53813601D88E}`
- Nachrichten:
  - `{DEC26699-97AD-BBF3-1764-2E443EC8E1C4}`: an VeSyncIO
  - `{36130503-6A92-BA52-AB31-6D24AFBAA9ED}`: an VeSyncConfig, VeSyncDevice

### Quellen

## 7. Versions-Historie

- 1.6 @ 19.04.2024 10:05
  - Fix: Korrektur der Variablenprofile 'VeSync.SpeedLevel123', 'VeSync.SpeedLevel1234'

- 1.5 @ 18.04.2024 07:29
  - Neu: Core 400S hinzugefügt
  - update submodule CommonStubs

- 1.4 @ 07.02.2024 17:46
  - Fix: Absicherung von Zugriffen auf andere Instanzen in Konfiguratoren
  - Änderung: Medien-Objekte haben zur eindeutigen Identifizierung jetzt ebenfalls ein Ident
  - Neu: Schalter, um Daten zu API-Aufrufen zu sammeln
    Die API-Aufruf-Daten stehen nun in einem Medienobjekt zur Verfügung
  - update submodule CommonStubs

- 1.3 @ 02.01.2024 11:56
  - Verbesserung: Umsetzung der numerischen Stufen von "AQLevel" in entsprechenden Text

- 1.2 @ 22.12.2023 10:43
  - Neu: Core 300S hinzugefügt

- 1.1 @ 09.12.2023 16:40
  - Fix: ein Gerät, das nicht erreichbar ist (z.B. stromlos), wurde als HTTP-Fehler gewertet

- 1.0 @ 05.12.2023 09:50
  - Initiale Version
