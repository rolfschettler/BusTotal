# Datenschutz und Aufbewahrung {#datenschutz}

Dieses Thema beschreibt, was die Software für Auskunft und Löschung personenbezogener Daten tut. Es ist keine Rechtsberatung. Klären Sie Fristen und Einzelfälle mit Ihrem Steuerberater und Ihrem Datenschutzbeauftragten.

## Auskunft: Daten exportieren {#export}

Verlangt ein Kunde Auskunft über seine gespeicherten Daten, öffnen Sie den Kunden und wählen unter **Weitere Aktionen** den Eintrag **Daten exportieren (DSGVO Auskunft)**. Sie erhalten eine Datei mit:

- den Stammdaten des Kunden,
- allen Ansprechpartnern und Adressen,
- dem Änderungsverlauf (welche Ereignisse wann stattgefunden haben).

Der Export steht Administrator, Disponent und Buchhaltung zur Verfügung. Jeder Export wird im [Änderungsprotokoll](#protokoll) festgehalten.

## Löschung: Anonymisieren {#anonymisieren}

Verlangt ein Kunde die Löschung, benutzen Sie **Anonymisieren (DSGVO Löschung)** unter **Weitere Aktionen**. Das dürfen **Administrator und Buchhaltung**, und es ist **nicht rückgängig zu machen**. Vor der Ausführung erscheint eine Bestätigung.

Dabei werden entfernt:

- Name, Anrede, E-Mail, Telefon, Mobil, Webseite, USt-IdNr., Adresse und Notizen des Kunden,
- alle Ansprechpartner und zusätzlichen Adressen.

Es bleiben bestehen:

- die **Kundennummer** und die **Debitorennummer**, denn Rechnungen müssen sie aufbewahren,
- der Datensatz selbst, als archivierter Kunde mit dem Namen „Anonymisiert“.

Im Änderungsprotokoll wird nur festgehalten, **dass** anonymisiert wurde, nicht welche Werte vorher darin standen.

> Wichtig: Das Änderungsprotokoll ist absichtlich unveränderlich. Ältere Einträge, die vor der Anonymisierung durch Änderungen entstanden sind, können frühere Feldwerte (zum Beispiel einen alten Ort) enthalten. Ob das mit Ihrer Aufbewahrungspflicht und der Löschpflicht vereinbar ist, klären Sie bitte mit Ihrem Datenschutzbeauftragten.

## Änderungsprotokoll {#protokoll}

Die Software führt ein Protokoll darüber, wer wann was geändert hat:

- Anlegen, Ändern und Löschen von Kunden, Ansprechpartnern, Adressen, Benutzern und Einstellungen, jeweils mit **altem und neuem Wert**,
- Anmeldungen, Abmeldungen und fehlgeschlagene Anmeldeversuche, mit IP-Adresse und Browser,
- Datenexporte und Anonymisierungen.

Passwörter werden nie ins Protokoll geschrieben. Einträge im Protokoll können **weder geändert noch gelöscht** werden. Das ist eine Anforderung der GoBD an die Nachvollziehbarkeit.

## Aufbewahrung von Rechnungen {#aufbewahrung}

Buchführungsunterlagen und Rechnungen müssen in Deutschland nach den gesetzlichen Fristen aufbewahrt werden. Deshalb:

- werden Kunden **archiviert statt gelöscht**,
- bleiben **Kunden- und Debitorennummer** auch nach einer Anonymisierung erhalten,
- werden Belegnummern [lückenlos](/hilfe/einstellungen#nummern-regeln) vergeben.

Die genauen Fristen nennt Ihnen Ihr Steuerberater.
