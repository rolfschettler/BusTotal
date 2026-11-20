import { parseHelp } from './help-parser';

describe('parseHelp: Verweise', () => {
  const html = (md: string) => parseHelp(md).html;

  it('öffnet externe Quellenangaben in einem neuen Tab, ohne Zugriff auf die Anwendung', () => {
    const out = html('# T {#t}\n\nSiehe [§ 23 FeV](https://www.gesetze-im-internet.de/fev_2010/__23.html).');
    expect(out).toContain('href="https://www.gesetze-im-internet.de/fev_2010/__23.html"');
    expect(out).toContain('target="_blank"');
    expect(out).toContain('rel="noopener noreferrer"');
  });

  it('lässt Verweise innerhalb der Hilfe im selben Fenster', () => {
    const out = html('# T {#t}\n\nSiehe [Fristen](/hilfe/fahrer#fristen) und [unten](#karte-auslesen).');
    expect(out).toContain('href="/hilfe/fahrer#fristen"');
    expect(out).toContain('href="#karte-auslesen"');
    expect(out).not.toContain('target=');
  });

  it('entfernt Schadcode weiterhin', () => {
    const out = html('# T {#t}\n\n<a href="javascript:alert(1)" onclick="alert(2)">x</a><script>alert(3)</script>');
    expect(out).not.toContain('javascript:');
    expect(out).not.toContain('onclick');
    expect(out).not.toContain('<script');
  });
});
