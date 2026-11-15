<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Allgemeine Geschäftsbedingungen (AGB) – Impressumsschutz für Creator. VERBINDLICHE FASSUNG (Deutsch).
 * Die englische Übersetzung (app/views/site/terms.php) dient nur der Information.
 * Wird die Fassung inhaltlich geändert: config('terms_version') in app/config.php erhöhen, damit Kunden neu zustimmen.
 *
 * @var array<string,string> $p  Anbieterdaten (siehe site_terms_vars())
 */
?>
        <?= site_headline('Allgemeine Geschäftsbedingungen', 'h2') ?>

        <div class="ed-element ed-text custom-theme site-page terms-doc" lang="de">
          <p class="terms-meta">Impressumsschutz für Creator · Version <?= h($p['version']) ?> · Stand: <?= h($p['date']) ?><br>
            <a href="/terms/">English translation (for information only)</a></p>

          <nav class="terms-toc" aria-label="Inhaltsverzeichnis">
            <strong>Inhalt</strong>
            <ol>
              <li><a href="#p1">Geltungsbereich, Vertragspartner</a></li>
              <li><a href="#p2">Leistungen</a></li>
              <li><a href="#p3">Registrierung und Vertragsschluss</a></li>
              <li><a href="#p4">Pflichten des Kunden</a></li>
              <li><a href="#p5">Datenschutz und Auskunft an Behörden</a></li>
              <li><a href="#p6">Entgelt</a></li>
              <li><a href="#p7">Laufzeit, Kündigung, Sperre</a></li>
              <li><a href="#p8">Rücktrittsrecht für Verbraucher</a></li>
              <li><a href="#p9">Haftung</a></li>
              <li><a href="#p10">Änderungen</a></li>
              <li><a href="#p11">Schlussbestimmungen</a></li>
              <li><a href="#anlage">Anlage: Muster-Rücktrittsformular</a></li>
            </ol>
          </nav>

          <section id="p1">
            <h3>§ 1 Geltungsbereich, Vertragspartner</h3>
            <ol>
              <li>Diese Allgemeinen Geschäftsbedingungen („AGB“) gelten für den Dienst „Impressumsschutz“ („Dienst“) von
                <strong><?= h($p['name']) ?></strong>, Inhaber Sebastian Wießhoff (Einzelunternehmen), <?= h($p['street']) ?>,
                <?= h($p['city']) ?>, Telefon <?= h($p['phone']) ?>, E-Mail <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>
                („Anbieter“), der über <?= h($p['host']) ?>, das Kundenportal und die zugehörigen Seiten angeboten wird. Die Angaben
                zum Anbieter finden sich auch im <a href="/legal-notice/">Impressum</a>.</li>
              <li>Der Dienst richtet sich ausschließlich an <strong>Creator</strong> („Kunden“). Creator sind natürliche oder juristische
                Personen, die für ein Publikum Inhalte veröffentlichen (zum Beispiel Videos, Streams, Podcasts, Kunst, Texte oder
                Social-Media-Kanäle) und den Dienst für diese Tätigkeit nutzen. Der Dienst steht allen Creatorn offen, die diese
                AGB akzeptieren und die Voraussetzungen des § 3 erfüllen.</li>
              <li>Kunden können Verbraucher im Sinne des § 1 Konsumentenschutzgesetz (KSchG) oder Unternehmer sein. Regelungen, die nur
                für Verbraucher oder nur für Unternehmer gelten, sind als solche gekennzeichnet.</li>
              <li>Abweichende oder ergänzende Bedingungen des Kunden gelten nur, wenn der Anbieter ihnen ausdrücklich in Textform
                zugestimmt hat.</li>
              <li>Nicht Gegenstand dieser AGB sind der Impressumsschutz für Online-Shops sowie Zahlungsleistungen für Shops (geplant über
                den Zahlungsdienstleister Stripe). Diese Leistungen werden nur auf Anfrage, in begrenzter Zahl und nach gesonderter,
                individueller Vereinbarung angeboten.</li>
            </ol>
          </section>

          <section id="p2">
            <h3>§ 2 Leistungen</h3>
            <ol>
              <li>Der Anbieter stellt dem Kunden unter <code><?= h($p['host']) ?>/legal/&lt;Seitenname&gt;/</code> eine Impressumsseite zur
                Verfügung. Auf dieser Seite werden anstelle der privaten Anschrift des Kunden Name und Anschrift des Anbieters als
                Management- und Kontaktadresse sowie die vom Kunden gewählten Angaben veröffentlicht (öffentlicher Name,
                Beschreibung des Geschäftszwecks, Links, auf Wunsch eine direkte Kontakt-E-Mail-Adresse). Die Impressumsseite
                nennt den Kunden als inhaltlich Verantwortlichen und den Anbieter als Management.</li>
              <li>Der Kunde pflegt seine Angaben im Kundenportal. Seine privaten Daten (Name, Anschrift, ggf. weitere Angaben zur
                Identität) werden nicht veröffentlicht, sondern nur zur Prüfung und zur Erfüllung gesetzlicher Pflichten
                verwendet (§ 5).</li>
              <li><strong>Prüfung und Freigabe:</strong> Jede erstmalige Veröffentlichung und jede Änderung der Impressumsseite wird
                vom Anbieter geprüft und freigegeben. Bis zur Freigabe einer Änderung bleibt die zuletzt freigegebene Fassung
                online. Der Anbieter darf eine Freigabe ablehnen, insbesondere wenn die Identität oder Anschrift des Kunden nicht
                ausreichend nachgewiesen ist, Angaben unvollständig oder offensichtlich unrichtig sind, Links auf rechtswidrige
                Inhalte führen oder der Verdacht eines Missbrauchs besteht. Der Anbieter teilt dem Kunden die Entscheidung per
                E-Mail mit.</li>
              <li><strong>Keine Rechtsberatung, keine Garantie:</strong> Der Dienst ist keine Rechtsberatung. Der Anbieter schuldet die
                Bereitstellung der Impressumsseite und der Management-Adresse, nicht aber, dass das Impressum den gesetzlichen
                Anforderungen jeder Rechtsordnung oder den Regeln jeder Plattform entspricht. Der Kunde bleibt für die Einhaltung
                seiner eigenen gesetzlichen Pflichten (zum Beispiel Informations- und Kennzeichnungspflichten) selbst
                verantwortlich und sollte bei Bedarf rechtlichen Rat einholen.</li>
              <li><strong>Management-Adresse:</strong> Die Adresse des Anbieters ist ausschließlich für behördliche und rechtliche
                Angelegenheiten bestimmt. Pakete, Waren, allgemeine Post und Kundenanfragen werden nicht angenommen; der Anbieter
                darf die Annahme verweigern. Behördliche oder gerichtliche Schreiben, die den Kunden betreffen und dem Anbieter
                zugehen, leitet der Anbieter an die im Kundenportal hinterlegte E-Mail-Adresse des Kunden weiter. Eine
                Zustellbevollmächtigung ist damit nicht verbunden.</li>
              <li>Der Kunde räumt dem Anbieter für die Vertragsdauer das nicht ausschließliche Recht ein, die von ihm eingegebenen
                öffentlichen Angaben auf der Impressumsseite zugänglich zu machen und dafür zu speichern und technisch zu
                vervielfältigen. Impressumsseiten können von Suchmaschinen und Archiven erfasst werden; auf Kopien Dritter hat der
                Anbieter keinen Einfluss.</li>
              <li>Der Anbieter bemüht sich um eine hohe Verfügbarkeit der Impressumsseite und des Kundenportals. Eine bestimmte
                Verfügbarkeit wird nicht zugesagt; Wartungsarbeiten und Störungen außerhalb des Einflussbereichs des Anbieters
                (zum Beispiel beim Hosting) bleiben vorbehalten.</li>
            </ol>
          </section>

          <section id="p3">
            <h3>§ 3 Registrierung und Vertragsschluss</h3>
            <ol>
              <li>Für die Nutzung ist eine Registrierung im Kundenportal mit E-Mail-Adresse, Name und Passwort erforderlich. Die
                E-Mail-Adresse muss über den zugesandten Link bestätigt werden. Der Dienst steht nur volljährigen Personen (ab
                18 Jahren) und juristischen Personen offen.</li>
              <li>Mit der Registrierung und der Zustimmung zu diesen AGB gibt der Kunde ein verbindliches Angebot auf Abschluss
                eines Nutzungsvertrags über den Dienst ab. Der Vertrag über die Impressumsseite kommt zustande, wenn der Anbieter die
                erste Impressumsseite des Kunden freigibt und den Kunden darüber per E-Mail informiert. Es besteht kein Anspruch
                auf Vertragsschluss oder Freigabe.</li>
              <li>Der Kunde hält seine Zugangsdaten geheim und schützt sie vor dem Zugriff Dritter. Bei Verdacht auf Missbrauch ändert er
                unverzüglich sein Passwort und informiert den Anbieter. Der Kunde haftet für Handlungen unter seinem Konto, soweit
                er den Zugriff Dritter zu vertreten hat.</li>
              <li>Der Kunde bestätigt bei der Registrierung, dass er den Dienst als Creator für eigene Kanäle oder Projekte nutzt.
                Die Zustimmung zu diesen AGB wird mit Zeitpunkt und Version der AGB gespeichert.</li>
            </ol>
          </section>

          <section id="p4">
            <h3>§ 4 Pflichten des Kunden</h3>
            <ol>
              <li>Der Kunde macht wahrheitsgemäße, vollständige und aktuelle Angaben (insbesondere zu Name und Anschrift) und
                aktualisiert sie unverzüglich bei Änderungen. Auf Verlangen weist er seine Identität und Anschrift mit einem
                geeigneten Nachweis nach.</li>
              <li>Der Kunde ist allein verantwortlich für die Inhalte seiner Kanäle, Websites und Veröffentlichungen sowie für die
                von ihm angegebenen Links. Links dürfen nur auf Seiten und Kanäle führen, die der Kunde selbst betreibt oder zu deren
                Verwendung er berechtigt ist.</li>
              <li>Der Kunde darf den Dienst nicht nutzen
                <ul>
                  <li>für rechtswidrige Inhalte oder Tätigkeiten, insbesondere Betrug, Hetze, Verletzungen von Urheber-, Marken- oder
                    Persönlichkeitsrechten, jugendgefährdende oder strafbare Inhalte;</li>
                  <li>um über seine Identität, seine Anschrift oder die Inhaberschaft von Kanälen zu täuschen;</li>
                  <li>für Dritte, die nicht selbst Kunde sind, oder zum Weiterverkauf;</li>
                  <li>um die Prüfung und Freigabe zu umgehen.</li>
                </ul>
              </li>
              <li>Der Dienst ist Creatorn vorbehalten. Betreiber von Online-Shops können den Impressumsschutz nur auf Anfrage und nach
                gesonderter Vereinbarung nutzen (§ 1 Abs. 5). Der Anbieter darf die Zahl der Impressumsprofile pro Konto begrenzen.</li>
              <li>Der Kunde hält seine im Portal hinterlegte E-Mail-Adresse erreichbar. Mitteilungen des Anbieters an diese Adresse
                gelten als zugegangen, sobald sie unter gewöhnlichen Umständen abgerufen werden können.</li>
              <li>Dritte können Verstöße gegen diese AGB oder Rechtsverletzungen über die Impressumsseiten per E-Mail an
                <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a> melden. Der Anbieter darf solche Hinweise prüfen und
                Maßnahmen nach § 7 Abs. 4 ergreifen.</li>
            </ol>
          </section>

          <section id="p5">
            <h3>§ 5 Datenschutz und Auskunft an Behörden</h3>
            <ol>
              <li>Der Anbieter verarbeitet personenbezogene Daten des Kunden zur Vertragserfüllung, zur Prüfung der Identität, zur Pflege
                der Impressumsseite, zur Kommunikation, zur Abwehr von Missbrauch und zur Erfüllung rechtlicher Pflichten. Einzelheiten
                enthält die <a href="/privacy/">Datenschutzerklärung</a>. Anfragen zum Datenschutz richtet der Kunde an
                <a href="mailto:<?= h($p['mail_privacy']) ?>"><?= h($p['mail_privacy']) ?></a>.</li>
              <li>Die privaten Daten des Kunden (Identität, Anschrift) werden nicht veröffentlicht. Zugriff haben nur der Anbieter und
                von ihm eingesetzte Dienstleister, soweit dies für den Betrieb erforderlich ist.</li>
              <li><strong>Impressumsschutz bedeutet Schutz vor der Veröffentlichung der Privatanschrift, nicht Anonymität gegenüber
                Behörden oder Gerichten.</strong> Der Kunde nimmt zur Kenntnis, dass der Anbieter die hinterlegten Identitäts- und
                Anschriftsdaten Behörden und Gerichten sowie – bei Vorliegen eines gesetzlichen Auskunftsanspruchs oder eines
                berechtigten Interesses, etwa bei behaupteten Rechtsverletzungen – Dritten offenlegen darf, soweit dies gesetzlich
                erforderlich oder behördlich beziehungsweise gerichtlich angeordnet ist. Der Anbieter informiert den Kunden darüber,
                soweit dies zulässig ist.</li>
              <li>Der Anbieter speichert die Daten während der Vertragsdauer. Danach werden sie gesperrt und gelöscht, sobald sie weder
                zur Erfüllung gesetzlicher Pflichten noch zur Durchsetzung oder Abwehr von Rechtsansprüchen benötigt werden.</li>
            </ol>
          </section>

          <section id="p6">
            <h3>§ 6 Entgelt</h3>
            <ol>
              <li>Ob und in welcher Höhe für den Dienst ein Entgelt zu zahlen ist, ergibt sich aus der Preisinformation, die dem Kunden
                bei Vertragsschluss im Kundenportal oder auf <?= h($p['host']) ?> angezeigt wird. Solange keine Preisinformation angezeigt und
                vom Kunden akzeptiert wurde, ist die Nutzung des Dienstes unentgeltlich.</li>
              <li>Preise gegenüber Verbrauchern verstehen sich inklusive, gegenüber Unternehmern exklusive der gesetzlichen Umsatzsteuer.
                Rechnungen sind, soweit nichts anderes angegeben ist, binnen 14 Tagen ohne Abzug zu zahlen. Bei Zahlungsverzug gelten die
                gesetzlichen Verzugszinsen.</li>
              <li>Entgelte können nur für die Zukunft und nur nach einer Ankündigung per E-Mail mindestens vier Wochen im Voraus
                geändert werden; § 10 Abs. 1 gilt entsprechend. Gegenüber Verbrauchern ist für Entgelterhöhungen die ausdrückliche
                Zustimmung erforderlich. Stimmt der Kunde nicht zu, kann er den Vertrag zum Wirksamwerden der Änderung kündigen.</li>
            </ol>
          </section>

          <section id="p7">
            <h3>§ 7 Laufzeit, Kündigung, Sperre</h3>
            <ol>
              <li>Der Vertrag wird auf unbestimmte Zeit geschlossen.</li>
              <li>Der Kunde kann den Vertrag jederzeit kündigen, zum Beispiel durch Offline-Nehmen seiner Impressumsseite im Kundenportal
                und Mitteilung per E-Mail an <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>. Bereits bezahlte Entgelte für
                laufende Abrechnungszeiträume werden, soweit gesetzlich zulässig, nicht anteilig erstattet.</li>
              <li>Der Anbieter kann den Vertrag mit einer Frist von 14 Tagen kündigen. Die Impressumsseite bleibt bis zum Ende der
                Kündigungsfrist online.</li>
              <li>Das Recht beider Seiten zur Kündigung aus wichtigem Grund bleibt unberührt. Ein wichtiger Grund liegt für den
                Anbieter insbesondere vor bei vorsätzlich falschen Angaben, bei Verstößen gegen § 4, bei rechtswidrigen oder
                rechtsverletzenden Inhalten, bei einer behördlichen oder gerichtlichen Anordnung, bei begründetem Missbrauchsverdacht
                und bei Zahlungsverzug trotz Mahnung. In diesen Fällen darf der Anbieter die Impressumsseite sofort offline nehmen
                oder sperren und das Konto sperren. Der Kunde wird darüber informiert, soweit dies zulässig ist.</li>
              <li>Mit Vertragsende wird die Impressumsseite offline genommen. Der Kunde sorgt rechtzeitig dafür, dass seine Kanäle ein
                anderes ordnungsgemäßes Impressum verwenden.</li>
            </ol>
          </section>

          <section id="p8">
            <h3>§ 8 Rücktrittsrecht für Verbraucher</h3>
            <p>Dieser Paragraph gilt nur für Kunden, die Verbraucher sind.</p>
            <ol>
              <li>Verbraucher können binnen 14 Tagen ohne Angabe von Gründen vom Vertrag zurücktreten. Die Rücktrittsfrist beträgt
                14 Tage ab dem Tag des Vertragsschlusses (§ 3 Abs. 2).</li>
              <li>Um das Rücktrittsrecht auszuüben, muss der Verbraucher dem Anbieter (<?= h($p['name']) ?>, <?= h($p['street']) ?>, <?= h($p['city']) ?>,
                E-Mail <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>) mittels einer eindeutigen Erklärung (zum Beispiel per Brief
                oder E-Mail) mitteilen, dass er vom Vertrag zurücktritt. Dazu kann das <a href="#anlage">Muster-Rücktrittsformular</a>
                verwendet werden, es ist aber nicht vorgeschrieben. Zur Wahrung der Frist genügt es, dass die Mitteilung vor Ablauf der
                Frist abgesendet wird.</li>
              <li>Im Fall eines wirksamen Rücktritts erstattet der Anbieter bereits geleistete Zahlungen unverzüglich, spätestens binnen
                14 Tagen ab Zugang der Rücktrittserklärung, mit demselben Zahlungsmittel.</li>
              <li>Verlangt der Verbraucher ausdrücklich, dass der Anbieter mit der Leistung (zum Beispiel der Prüfung und Freigabe der
                Impressumsseite) schon vor Ablauf der Rücktrittsfrist beginnt, und tritt er danach zurück, so zahlt er einen
                angemessenen Betrag für die bis dahin erbrachte Leistung. Ist der Dienst unentgeltlich, entsteht kein Zahlungsanspruch.</li>
              <li>Das Rücktrittsrecht erlischt bei Dienstleistungen, sobald der Anbieter die Leistung vollständig erbracht hat, wenn er
                mit der Ausführung erst begonnen hat, nachdem der Verbraucher ausdrücklich zugestimmt und seine Kenntnis davon bestätigt
                hat, dass er sein Rücktrittsrecht bei vollständiger Vertragserfüllung verliert.</li>
            </ol>
          </section>

          <section id="p9">
            <h3>§ 9 Haftung</h3>
            <ol>
              <li>Der Anbieter haftet unbeschränkt für Schäden aus der Verletzung des Lebens, des Körpers oder der Gesundheit sowie für
                Vorsatz und grobe Fahrlässigkeit.</li>
              <li>Gegenüber Verbrauchern haftet der Anbieter im Übrigen nach den gesetzlichen Bestimmungen.</li>
              <li>Gegenüber Unternehmern ist die Haftung für leichte Fahrlässigkeit ausgeschlossen, ebenso die Haftung für entgangenen
                Gewinn, mittelbare Schäden und Folgeschäden, soweit gesetzlich zulässig. Regressforderungen nach § 12 Produkthaftungsgesetz
                sind ausgeschlossen.</li>
              <li>Der Anbieter haftet nicht für Inhalte des Kunden, für verlinkte Seiten Dritter und für die Folgen unrichtiger oder
                veralteter Angaben des Kunden. Für Unterbrechungen aufgrund höherer Gewalt oder aufgrund von Ausfällen Dritter (zum
                Beispiel Hosting- oder Mail-Anbieter) haftet der Anbieter nur bei Vorsatz oder grober Fahrlässigkeit.</li>
              <li>Der Kunde stellt den Anbieter von Ansprüchen Dritter (einschließlich angemessener Kosten der Rechtsverteidigung)
                frei, die auf rechtswidrigen Inhalten oder Angaben des Kunden oder auf einer schuldhaften Verletzung dieser AGB
                beruhen, soweit der Kunde die Rechtsverletzung zu vertreten hat.</li>
            </ol>
          </section>

          <section id="p10">
            <h3>§ 10 Änderungen</h3>
            <ol>
              <li>Der Anbieter darf diese AGB ändern, wenn dafür ein sachlicher Grund besteht (zum Beispiel geänderte Rechtslage,
                Weiterentwicklung des Dienstes oder Missbrauchsabwehr). Er teilt die Änderung dem Kunden mindestens vier Wochen vor
                dem Inkrafttreten per E-Mail mit und weist auf das Recht zum Widerspruch und die Folgen hin. Widerspricht der Kunde
                nicht innerhalb dieser Frist in Textform, gelten die Änderungen als genehmigt. Bei Widerspruch können beide Seiten
                den Vertrag zum Zeitpunkt des Inkrafttretens kündigen. Für Änderungen der Hauptleistungen und des Entgelts ist
                bei Verbrauchern die ausdrückliche Zustimmung erforderlich.</li>
              <li>Der Anbieter kann zusätzlich verlangen, dass der Kunde der jeweils aktuellen Fassung im Kundenportal ausdrücklich
                zustimmt. Bis zur Zustimmung können das Anlegen neuer Impressumsprofile und das Einreichen von Änderungen gesperrt
                sein; die zuletzt freigegebene Impressumsseite bleibt online.</li>
              <li>Der Anbieter darf den Dienst weiterentwickeln und ändern, soweit der Kunde dadurch nicht unzumutbar benachteiligt wird.
                Die Einstellung des gesamten Dienstes kündigt der Anbieter mindestens drei Monate im Voraus an.</li>
            </ol>
          </section>

          <section id="p11">
            <h3>§ 11 Schlussbestimmungen</h3>
            <ol>
              <li>Es gilt österreichisches Recht unter Ausschluss des UN-Kaufrechts und der Verweisungsnormen des internationalen
                Privatrechts. Gegenüber Verbrauchern gilt diese Rechtswahl nur, soweit dadurch keine zwingenden Verbraucherschutz-
                vorschriften des Staates entzogen werden, in dem der Verbraucher seinen gewöhnlichen Aufenthalt hat.</li>
              <li>Für Unternehmer ist das sachlich zuständige Gericht in Graz ausschließlich zuständig. Für Verbraucher gelten die
                gesetzlichen Gerichtsstände.</li>
              <li>Erklärungen und Mitteilungen können, soweit das Gesetz keine strengere Form verlangt, in Textform (zum Beispiel per
                E-Mail) abgegeben werden.</li>
              <li>Vertragssprache ist Deutsch. Die englische Übersetzung dieser AGB dient nur der Information; bei Abweichungen geht
                die deutsche Fassung vor.</li>
              <li>Bei Streitigkeiten bemühen sich die Parteien um eine einvernehmliche Lösung. Verbraucher können sich an eine
                anerkannte Verbraucherschlichtungsstelle wenden; der Anbieter ist zur Teilnahme an einem
                Streitbeilegungsverfahren nicht verpflichtet.</li>
              <li>Sollten einzelne Bestimmungen dieser AGB unwirksam sein oder werden, bleibt die Wirksamkeit der übrigen Bestimmungen
                unberührt. An die Stelle der unwirksamen Bestimmung tritt die gesetzlich zulässige Regelung, die ihrem wirtschaftlichen
                Zweck am nächsten kommt.</li>
            </ol>
          </section>

          <section id="anlage">
            <h3>Anlage: Muster-Rücktrittsformular</h3>
            <p>(Wenn Sie vom Vertrag zurücktreten wollen, können Sie dieses Formular ausfüllen und an uns zurücksenden.)</p>
            <p>An <?= h($p['name']) ?>, <?= h($p['street']) ?>, <?= h($p['city']) ?>, E-Mail <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>:</p>
            <ul>
              <li>Hiermit trete ich von dem von mir abgeschlossenen Vertrag über die Erbringung der folgenden Dienstleistung zurück:
                Impressumsschutz.</li>
              <li>Bestellt am / Konto angelegt am: ______________________</li>
              <li>Name des/der Verbraucher(s): ______________________</li>
              <li>Anschrift des/der Verbraucher(s): ______________________</li>
              <li>Datum und, bei Mitteilung auf Papier, Unterschrift: ______________________</li>
            </ul>
          </section>

          <p class="terms-footnote">Fragen zu diesen AGB: <a href="mailto:<?= h($p['mail_contact']) ?>"><?= h($p['mail_contact']) ?></a>.
            <a href="/">Zur Startseite</a> · <a href="/imprint-protection/">Impressumsschutz</a> · <a href="/privacy/">Datenschutz</a></p>
        </div>
