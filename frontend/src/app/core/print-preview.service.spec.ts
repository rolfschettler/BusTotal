import { HttpErrorResponse } from '@angular/common/http';
import { Subject, of, throwError } from 'rxjs';
import { DIRECT_PRINT_ON_DESKTOP, PrintPreviewService, isDesktopDevice } from './print-preview.service';

describe('PrintPreviewService', () => {

  it('öffnet sofort (lädt) und zeigt danach das PDF', () => {
    const s = new PrintPreviewService();
    const answer = new Subject<Blob>();
    s.open({ title: 'Rechnung RE-1', filename: 'RE-1.pdf', load: () => answer });
    expect(s.state()).toBe('loading');
    expect(s.title()).toBe('Rechnung RE-1');

    answer.next(new Blob(['%PDF-1.4']));
    expect(s.state()).toBe('ready');
    expect(s.blob()?.type).toBe('application/pdf');
  });

  it('zeigt Serverfehler als Text in der Vorschau', () => {
    const s = new PrintPreviewService();
    s.open({ title: 'x', filename: 'x.pdf', load: () => throwError(() => new HttpErrorResponse({ status: 0 })) });
    expect(s.state()).toBe('error');
    expect(s.error()).toBeTruthy();
    expect(s.blob()).toBeNull();
  });

  it('schließen räumt auf, eine veraltete Antwort öffnet nichts mehr', () => {
    const s = new PrintPreviewService();
    const late = new Subject<Blob>();
    s.open({ title: 'x', filename: 'x.pdf', load: () => late });
    s.close();
    late.next(new Blob(['%PDF-1.4']));
    expect(s.state()).toBe('closed');
    expect(s.blob()).toBeNull();
  });

  it('ein zweites Öffnen ersetzt das erste', () => {
    const s = new PrintPreviewService();
    const first = new Subject<Blob>();
    s.open({ title: 'eins', filename: '1.pdf', load: () => first });
    s.open({ title: 'zwei', filename: '2.pdf', load: () => of(new Blob(['%PDF-1.4'])) });
    first.next(new Blob(['alt']));
    expect(s.title()).toBe('zwei');
    expect(s.state()).toBe('ready');
  });

  it('fail meldet einen Darstellungsfehler, Herunterladen bleibt möglich', () => {
    const s = new PrintPreviewService();
    s.open({ title: 'x', filename: 'x.pdf', load: () => of(new Blob(['%PDF-1.4'])) });
    s.fail('Vorschau nicht möglich');
    expect(s.state()).toBe('error');
    expect(s.blob()).not.toBeNull();
  });

  it('Direktdruck gilt nur am PC und endet beim Schließen', () => {
    const s = new PrintPreviewService();
    s.open({ title: 'x', filename: 'x.pdf', load: () => of(new Blob(['%PDF-1.4'])) });
    expect(s.direct()).toBe(DIRECT_PRINT_ON_DESKTOP && isDesktopDevice());
    s.close();
    expect(s.direct()).toBe(false);
  });
});
