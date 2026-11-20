<?php

namespace App\Http\Controllers\Concerns;

use App\Routing\RoutingException;
use Illuminate\Http\JsonResponse;

/**
 * Fehler des Routing- und Adressdienstes als Antwort mit deutscher Meldung, bewusst NICHT als 5xx: Das Frontend deutet 502/503/504
 * als "Server nicht erreichbar" (siehe httpErrorText). Hier ist unser Server erreichbar, der Fremddienst nicht.
 *  - 422: am eingegebenen Ort liegt es
 *  - 424: der Dienst selbst (nicht eingerichtet, Kontingent erreicht, antwortet nicht)
 */
trait RespondsToRoutingFailure
{
    private function failure(RoutingException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'reason' => $e->kind], $e->isInputProblem() ? 422 : 424);
    }
}
