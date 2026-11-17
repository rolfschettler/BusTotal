# Benutzer und Passwörter {#benutzer}

Dieses Thema erklärt, wie Benutzer verwaltet werden (nur für den **Administrator**) und wie jeder Benutzer sein eigenes Passwort ändert.

## Benutzerliste {#benutzerliste}

Unter **Benutzer** in der Seitenleiste sehen Sie alle Personen, die sich anmelden können:

- **Name und E-Mail-Adresse.** Die E-Mail-Adresse ist der Anmeldename und darf nur einmal vorkommen.
- **Rolle.** Sie bestimmt, was der Benutzer sehen und ändern darf. Die Rechte jeder Rolle stehen unter [Rollen und Rechte](/hilfe/anmeldung#rollen).
- **Status.** *Aktiv* oder *Gesperrt*. Das Kennzeichen *Vorläufiges Passwort* zeigt, dass sich der Benutzer noch nicht mit einem eigenen Passwort angemeldet hat.
- **Letzte Anmeldung.** Wann sich der Benutzer zuletzt erfolgreich angemeldet hat, oder „noch nie“.

Ihre eigene Zeile ist mit **Sie** gekennzeichnet.

Bedienung der Liste:

- **Suchen und filtern:** Das Suchfeld durchsucht Name, E-Mail-Adresse und Rolle, die Treffer sind gelb markiert. Dazu gibt es Filter für **Rolle** und **Status**. Der Zähler oben zeigt dann „2 von 4 Benutzer“. Groß- und Kleinschreibung sowie Umlaute spielen keine Rolle.
- **Sortieren:** Ein Klick auf eine Spaltenüberschrift sortiert die Liste, ein zweiter dreht die Richtung um. „Noch nie angemeldet“ steht in beiden Richtungen am Ende.
- **Symbole in der ersten Spalte:** der **Stift** bearbeitet den Benutzer, der **Schlüssel** setzt das Passwort zurück, das **Schloss** sperrt (bei einem gesperrten Benutzer öffnet das geöffnete Schloss die Sperre wieder). Bei Ihrer eigenen Zeile sind Schlüssel und Schloss blass und nicht bedienbar, denn Sie können sich nicht selbst sperren oder zurücksetzen.
- Der Kopf mit Suche und Spaltenüberschriften bleibt beim Scrollen sichtbar.

## Benutzer anlegen {#anlegen}

1. Wählen Sie **Neuer Benutzer**.
2. Tragen Sie Name, E-Mail-Adresse und Rolle ein.
3. Wählen Sie **Anlegen**.

Das System erzeugt ein [vorläufiges Passwort](#vorlaeufiges-passwort) und zeigt es Ihnen einmalig an. Sie können kein Passwort selbst festlegen, damit nicht schwache Passwörter entstehen.

### Das vorläufige Passwort {#vorlaeufiges-passwort}

- Es wird **nur ein einziges Mal angezeigt**, direkt nach dem Anlegen oder Zurücksetzen. Mit **Kopieren** übernehmen Sie es in die Zwischenablage.
- Es ist **nirgends gespeichert**, auch nicht lesbar für Sie oder die Software-Anbieter. Haben Sie es nicht notiert, setzen Sie das Passwort einfach [zurück](#zuruecksetzen).
- Geben Sie es dem Benutzer **persönlich** weiter, am besten nicht per E-Mail.
- Beim ersten Anmelden **muss** der Benutzer ein eigenes Passwort vergeben. Bis dahin kann er nichts anderes tun.

## Benutzer bearbeiten {#bearbeiten}

Mit dem **Stift** ändern Sie Name, E-Mail-Adresse und Rolle. Das Formular erscheint oberhalb der Liste. Die Änderung gilt sofort, der Benutzer muss sich dafür nicht neu anmelden. Alle Änderungen stehen mit altem und neuem Wert im [Änderungsprotokoll](/hilfe/datenschutz#protokoll).

Ihre **eigene Rolle** können Sie nicht ändern, damit Sie sich nicht versehentlich selbst die Rechte nehmen.

## Sperren und entsperren {#sperren}

Mit dem **Schloss** (Sperren) verliert ein Benutzer sofort den Zugang. Sie müssen es in einer Rückfrage bestätigen.

- Er wird **auf allen Geräten abgemeldet** und kann sich nicht mehr anmelden.
- Seine Daten und seine Einträge im Änderungsprotokoll bleiben erhalten.
- Mit dem geöffneten Schloss (**Entsperren**, ohne Rückfrage) kann er sich später wieder anmelden, mit seinem bisherigen Passwort.

Benutzer können **nicht gelöscht** werden. Das Änderungsprotokoll verweist auf sie, und es muss nach den Grundsätzen ordnungsmäßiger Buchführung (GoBD) vollständig bleiben. Für ausgeschiedene Mitarbeiter ist Sperren der richtige Weg.

Sie können sich **nicht selbst sperren**. Außerdem bleibt immer mindestens ein aktiver Administrator erhalten, damit sich niemand aussperren kann.

## Passwort zurücksetzen {#zuruecksetzen}

Hat jemand sein Passwort vergessen, klicken Sie auf den **Schlüssel** (Passwort zurücksetzen) und bestätigen die Rückfrage:

- Das bisherige Passwort wird ungültig, der Benutzer wird überall abgemeldet.
- Sie erhalten ein neues [vorläufiges Passwort](#vorlaeufiges-passwort), das er beim nächsten Anmelden ändern muss.

Für sich selbst können Sie diese Funktion nicht benutzen. Ändern Sie Ihr eigenes Passwort im Benutzermenü.

## Das eigene Passwort ändern {#passwort-aendern}

Jeder Benutzer ändert sein Passwort über das **Benutzermenü** oben rechts: **Passwort ändern**.

1. Geben Sie Ihr aktuelles Passwort ein.
2. Geben Sie das neue Passwort zweimal ein.

Das neue Passwort muss **mindestens 10 Zeichen** lang sein und **mindestens einen Buchstaben und eine Ziffer** enthalten. Es muss sich vom bisherigen unterscheiden.

Nach der Änderung werden Sie auf **allen anderen Geräten abgemeldet**. Auf dem Gerät, an dem Sie das Passwort ändern, bleiben Sie angemeldet.

Zu viele falsche Eingaben des aktuellen Passworts sperren die Funktion kurz. Warten Sie dann eine Minute.

> Wählen Sie ein Passwort, das Sie nirgends sonst verwenden, und geben Sie es niemandem weiter, auch nicht dem Administrator. Er braucht es nie.
