<?php

namespace App\Routing;

use RuntimeException;

/**
 * Fehler des Routing- oder Adressdienstes. Die Meldung ist deutsch und für Anwender gedacht (kein Schlüssel,
 * keine Adresse des Dienstes, keine Rohantwort).
 */
class RoutingException extends RuntimeException
{
    public const NOT_CONFIGURED = 'not_configured'; // kein Zugangsschlüssel eingetragen
    public const NO_ROUTE = 'no_route';             // Punkt nicht erreichbar / keine Straße in der Nähe
    public const REJECTED = 'rejected';             // Anfrage vom Dienst abgelehnt (zu lang, zu viele Punkte ...)
    public const LIMIT = 'limit';                   // Kontingent oder Anfragegrenze erreicht
    public const UNAVAILABLE = 'unavailable';       // Dienst antwortet nicht oder mit einem Fehler
    public const OUTDATED = 'outdated';             // Adresse des Dienstes abgeschaltet oder verlegt (410, Weiterleitung): nur der Betreiber kann es beheben
    public const AUTH = 'auth';                     // Zugangsschlüssel abgelehnt (abgelaufen, gesperrt, falsch)
    public const UNEXPECTED = 'unexpected';         // Anfrage vom Dienst nicht verstanden oder Antwort nicht lesbar: Fehler der Anwendung

    public function __construct(string $message, public readonly string $kind)
    {
        parent::__construct($message);
    }

    /** Ob der Fehler an den eingegebenen Orten liegt (der Anwender kann etwas ändern). */
    public function isInputProblem(): bool
    {
        return in_array($this->kind, [self::NO_ROUTE, self::REJECTED], true);
    }
}
