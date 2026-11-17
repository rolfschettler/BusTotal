<?php

namespace App\Tenancy;

use RuntimeException;

/** Fehler beim Anlegen oder Pflegen einer Firma; die Meldung ist für Menschen gedacht. */
class TenantException extends RuntimeException {}
