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
