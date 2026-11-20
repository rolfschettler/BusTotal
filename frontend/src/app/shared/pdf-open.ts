/**
 * Öffnet ein PDF (als Blob vom Server) in einem neuen Tab. Das Fenster wird SOFORT beim Klick geöffnet und erst nach der
 * Antwort des Servers befüllt: Browser erlauben ein neues Fenster nur direkt auf einen Klick, nicht nach einer Wartezeit.
 * Wird das Fenster dennoch blockiert, startet stattdessen ein Download.
 */
export function openPdfWindow(): Window | null {
  try {
    return window.open('', '_blank');
  } catch {
    return null;
  }
}

export function showPdf(blob: Blob, target: Window | null, filename: string): void {
  const url = URL.createObjectURL(new Blob([blob], { type: 'application/pdf' }));
  if (target && !target.closed) {
    target.location.href = url;
  } else {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
  }
  // Das Fenster braucht die Adresse noch einen Moment zum Laden.
  setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
