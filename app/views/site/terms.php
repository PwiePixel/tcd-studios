<?php
declare(strict_types=1);

defined('TCD_APP') || exit;

/**
 * Terms and Conditions (AGB) – Imprint Protection for Creators. ENGLISH CONVENIENCE TRANSLATION (for information only).
 * The binding version is the German text (app/views/site/agb.php). Keep both files structurally identical
 * (same sections, same paragraph numbering, so that cross references such as "§ 7 para. 4" stay valid).
 * If the German version is changed in substance: raise config('terms_version') in app/config.php so that customers
 * have to agree again, and update this translation accordingly.
 *
 * @var array<string,string> $p  Provider data (see site_terms_vars())
 */
?>
        <?= site_headline('Terms and Conditions', 'h2') ?>

        <div class="ed-element ed-text custom-theme site-page terms-doc" lang="en">
          <p class="terms-meta">Convenience translation, for information only. The <a href="/agb/">German version (Allgemeine Geschäftsbedingungen)</a>
            is the binding version and governs.<br>
            Imprint Protection for Creators · Version <?= h($p['version']) ?> · Date: <?= h($p['date']) ?></p>

          <nav class="terms-toc" aria-label="Table of contents">
            <strong>Contents</strong>
            <ol>
              <li><a href="#p1">Scope of Application, Contracting Party</a></li>
              <li><a href="#p2">Services</a></li>
              <li><a href="#p3">Registration and Conclusion of Contract</a></li>
              <li><a href="#p4">Obligations of the Customer</a></li>
              <li><a href="#p5">Data Protection and Disclosure to Authorities</a></li>
              <li><a href="#p6">Fees</a></li>
              <li><a href="#p7">Term, Termination, Suspension</a></li>
              <li><a href="#p8">Right of Withdrawal for Consumers</a></li>
              <li><a href="#p9">Liability</a></li>
              <li><a href="#p10">Amendments</a></li>
              <li><a href="#p11">Final Provisions</a></li>
              <li><a href="#anlage">Annex: Model Withdrawal Form</a></li>
            </ol>
          </nav>

          <section id="p1">
            <h3>§ 1 Scope of Application, Contracting Party</h3>
            <ol>
              <li>These Terms and Conditions (“Terms”) apply to the “Imprint Protection” service (“Service”) of
                <strong><?= h($p['name']) ?></strong>, proprietor Sebastian Wießhoff (sole proprietorship, “Einzelunternehmen”), <?= h($p['street']) ?>,
                <?= h($p['city']) ?>, telephone <?= h($p['phone']) ?>, e-mail <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>
                (“Provider”), which is offered via <?= h($p['host']) ?>, the customer portal and the associated pages. The details
                of the Provider can also be found in the <a href="/legal-notice/">Legal Notice (Impressum)</a>.</li>
              <li>The Service is aimed exclusively at <strong>Creators</strong> (“Customers”). Creators are natural persons or legal
                entities who publish content for an audience (for example videos, streams, podcasts, art, texts or
                social media channels) and use the Service for this activity. The Service is open to all Creators who accept these
                Terms and meet the requirements of § 3.</li>
              <li>Customers may be consumers within the meaning of § 1 of the Consumer Protection Act (Konsumentenschutzgesetz, KSchG)
                or entrepreneurs (“business customers”). Provisions that apply only to consumers or only to business customers are
                marked as such.</li>
              <li>Deviating or supplementary terms of the Customer apply only if the Provider has expressly agreed to them in text
                form.</li>
              <li>These Terms do not cover Imprint Protection for online shops or payment services for shops (planned via the payment
                service provider Stripe). These services are offered only on request, in limited numbers and subject to a separate,
                individual agreement.</li>
            </ol>
          </section>

          <section id="p2">
            <h3>§ 2 Services</h3>
            <ol>
              <li>The Provider makes an imprint page available to the Customer at <code><?= h($p['host']) ?>/legal/&lt;page-name&gt;/</code>.
                On this page, instead of the Customer’s private address, the name and address of the Provider are published as
                management and contact address, together with the details chosen by the Customer (public name,
                description of the business purpose, links and, on request, a direct contact e-mail address). The imprint page
                names the Customer as the person responsible for the content and the Provider as the management.</li>
              <li>The Customer maintains his or her details in the customer portal. The Customer’s private data (name, address and, where
                applicable, further information on identity) are not published but are used only for review and for compliance with
                legal obligations (§ 5).</li>
              <li><strong>Review and approval:</strong> Every first publication and every change of the imprint page is reviewed and
                approved by the Provider. Until a change is approved, the most recently approved version remains
                online. The Provider may refuse approval, in particular where the Customer’s identity or address has not been
                sufficiently proven, details are incomplete or obviously incorrect, links lead to unlawful
                content or abuse is suspected. The Provider notifies the Customer of the decision by
                e-mail.</li>
              <li><strong>No legal advice, no guarantee:</strong> The Service is not legal advice. The Provider owes the
                provision of the imprint page and the management address, but not that the imprint complies with the legal
                requirements of every jurisdiction or the rules of every platform. The Customer remains responsible for
                compliance with his or her own legal obligations (for example information and labelling obligations) and should
                seek legal advice where necessary.</li>
              <li><strong>Management address:</strong> The Provider’s address is intended exclusively for official and legal
                matters. Parcels, goods, general mail and customer enquiries are not accepted; the Provider may refuse
                acceptance. Official or court correspondence concerning the Customer that reaches the Provider is forwarded by the
                Provider to the Customer’s e-mail address stored in the customer portal. No authorisation to accept service of
                documents (Zustellbevollmächtigung) is associated with this.</li>
              <li>For the duration of the contract, the Customer grants the Provider the non-exclusive right to make the public details
                entered by the Customer accessible on the imprint page and, for that purpose, to store and technically reproduce
                them. Imprint pages may be captured by search engines and archives; the Provider has no influence over copies
                made by third parties.</li>
              <li>The Provider endeavours to ensure high availability of the imprint page and the customer portal. No particular
                availability is promised; maintenance work and disruptions outside the Provider’s sphere of influence
                (for example at the hosting provider) remain reserved.</li>
            </ol>
          </section>

          <section id="p3">
            <h3>§ 3 Registration and Conclusion of Contract</h3>
            <ol>
              <li>Use of the Service requires registration in the customer portal with an e-mail address, name and password. The
                e-mail address must be confirmed via the link sent to it. The Service is open only to adults (aged
                18 or over) and to legal entities.</li>
              <li>By registering and consenting to these Terms, the Customer submits a binding offer to conclude a user agreement
                for the Service. The contract for the imprint page is concluded when the Provider approves the
                Customer’s first imprint page and informs the Customer of this by e-mail. There is no entitlement
                to the conclusion of a contract or to approval.</li>
              <li>The Customer keeps his or her access credentials secret and protects them from access by third parties. If misuse
                is suspected, the Customer changes his or her password without undue delay and informs the Provider. The Customer is
                liable for actions taken under his or her account to the extent that the Customer is responsible for
                the third-party access.</li>
              <li>On registration, the Customer confirms that he or she uses the Service as a Creator for his or her own channels or
                projects. Consent to these Terms is recorded together with the time and the version of the Terms.</li>
            </ol>
          </section>

          <section id="p4">
            <h3>§ 4 Obligations of the Customer</h3>
            <ol>
              <li>The Customer provides truthful, complete and up-to-date details (in particular regarding name and address) and
                updates them without undue delay when they change. The Customer proves his or her identity and address by a photo of
                his or her ID card (front and back), which the Customer uploads through the encrypted form in the customer portal.
                This proof must be provided before the imprint page is first published and again whenever the name or address
                changes. The Provider may require further evidence where necessary. The Customer may cover details on the ID card
                that are not needed for the check (for example the photo, the document number or the access number); the name and
                the address must remain legible. The handling of the ID-card photo is governed by § 5.</li>
              <li>The Customer is solely responsible for the content of his or her channels, websites and publications and for the
                links provided by the Customer. Links may lead only to pages and channels that the Customer operates himself or herself or
                is entitled to use.</li>
              <li>The Customer may not use the Service
                <ul>
                  <li>for unlawful content or activities, in particular fraud, incitement to hatred, infringements of copyright,
                    trademark or personality rights, content harmful to minors or content constituting a criminal offence;</li>
                  <li>to deceive others about his or her identity, address or ownership of channels;</li>
                  <li>for third parties who are not themselves Customers, or for resale;</li>
                  <li>to circumvent the review and approval.</li>
                </ul>
              </li>
              <li>The Service is reserved for Creators. Operators of online shops may use Imprint Protection only on request and
                under a separate agreement (§ 1 para. 5). The Provider may limit the number of imprint profiles per account.</li>
              <li>The Customer keeps the e-mail address stored in the portal reachable. Notifications from the Provider to this address
                are deemed received as soon as they can be retrieved under ordinary circumstances.</li>
              <li>Third parties may report violations of these Terms or infringements of rights via the imprint pages by e-mail to
                <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>. The Provider may examine such reports and
                take measures under § 7 para. 4.</li>
            </ol>
          </section>

          <section id="p5">
            <h3>§ 5 Data Protection and Disclosure to Authorities</h3>
            <ol>
              <li>The Provider processes the Customer’s personal data for performance of the contract, verification of identity,
                maintenance of the imprint page, communication, prevention of abuse and compliance with legal obligations. Details
                are set out in the <a href="/privacy/">Privacy Policy</a>. The Customer directs data protection enquiries to
                <a href="mailto:<?= h($p['mail_privacy']) ?>"><?= h($p['mail_privacy']) ?></a>.</li>
              <li>The Customer’s private data (identity, address) are not published. The address data are stored in encrypted form.
                Access is limited to the Provider and the service providers engaged by the Provider, to the extent necessary for
                operation.</li>
              <li><strong>Imprint Protection means protection against publication of the private address, not anonymity towards
                authorities or courts.</strong> The Customer acknowledges that the Provider may disclose the identity and address data
                on file to authorities and courts and – where a statutory right to information or a legitimate interest exists,
                for example in the case of alleged infringements of rights – to third parties, to the extent that this is
                required by law or ordered by an authority or a court, as the case may be. The Provider informs the Customer of this
                to the extent permitted.</li>
              <li><strong>ID-card photo:</strong> The photo is transmitted over an encrypted connection (HTTPS) and is encrypted
                immediately upon upload with a public key. The matching private key is not stored on any server; the web server
                therefore cannot read the photo after the upload. Authorised persons of the Provider decrypt the photo solely for
                the check, in their own browser, and compare it with the details entered by the Customer. After the check the
                photo is deleted from the web server; it is kept only in encrypted form on a separate, access-restricted storage
                system until the Service ends.</li>
              <li><strong>Purpose of storage:</strong> The Provider supplies the Customer only with the management address (mailbox)
                and protection against publication of the private address. If abuse occurs, for example unlawful content, claims
                of third parties or requests from authorities, the Provider must be able to name the responsible person. For this
                reason the Provider stores the identity and address data and the ID-card photo; disclosure is governed by
                para. 3.</li>
              <li>The Provider stores the address data and the ID-card photo for as long as the Service is used. When the Service
                ends, they are deleted in accordance with § 7 paras. 6 and 7.</li>
              <li><strong>Legal hold:</strong> Where a reason exists that obliges or entitles the Provider to retain data (for
                example an open legal claim, a request from an authority or a statutory retention duty), the Provider may suspend
                the deletion of the data concerned. The data are then kept only for that reason and are deleted as soon as the
                reason no longer applies. The Customer is informed of this to the extent permitted.</li>
            </ol>
          </section>

          <section id="p6">
            <h3>§ 6 Fees</h3>
            <ol>
              <li>Whether and in what amount a fee is payable for the Service follows from the price information displayed to the
                Customer when the contract is concluded in the customer portal or on <?= h($p['host']) ?>. As long as no price information
                has been displayed and accepted by the Customer, use of the Service is free of charge.</li>
              <li>Prices are inclusive of statutory value added tax for consumers and exclusive of statutory value added tax for
                business customers. Unless otherwise stated, invoices are payable within 14 days without deduction. In the event of
                late payment, statutory default interest applies.</li>
              <li>Fees may be changed only for the future and only after notice by e-mail given at least four weeks in advance;
                § 10 para. 1 applies accordingly. For consumers, express consent is required for fee increases. If the Customer does
                not consent, the Customer may terminate the contract with effect from the time the change takes effect.</li>
            </ol>
          </section>

          <section id="p7">
            <h3>§ 7 Term, Termination, Suspension</h3>
            <ol>
              <li>The contract is concluded for an indefinite period.</li>
              <li><strong>Ending by the Customer:</strong> The Customer may end the contract at any time. To do so, the Customer
                requests the end of the Service in the customer portal. This creates a deletion request in the Provider’s system,
                and the Provider sends the Customer a time-limited confirmation link by e-mail. The Customer confirms the deletion
                request via this link and thereby declares that the Service ends, that all of his or her data will be deleted
                irrevocably and that he or she may no longer use the Service afterwards. The contract ends with the confirmation,
                the imprint page is taken offline immediately, and the Customer’s data are deleted in accordance with para. 6.
                Without confirmation nothing changes; merely taking the imprint page offline ends neither the contract nor the
                storage of the data. Termination in text form (for example by e-mail to
                <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>) remains possible; para. 7 then applies to the deletion of the data.
                Fees already paid for current billing periods are not refunded pro rata, to the extent permitted by law.</li>
              <li>The Provider may terminate the contract by giving 14 days’ notice. The imprint page remains online until the end of the
                notice period.</li>
              <li>The right of both parties to terminate for good cause remains unaffected. Good cause exists for the
                Provider in particular in the case of intentionally false details, violations of § 4, unlawful or
                infringing content, an order by an authority or a court, well-founded suspicion of abuse
                and late payment despite a reminder. In these cases the Provider may take the imprint page offline or
                suspend it immediately and suspend the account. The Customer is informed of this to the extent permitted.</li>
              <li>When the contract ends, the imprint page is taken offline. The Customer ensures in good time that his or her channels
                use another proper imprint. After the contract has ended, the Customer may no longer use the Service.</li>
              <li><strong>Deletion after confirmation by the Customer:</strong> Upon the confirmation under para. 2, the Provider
                deletes all of the Customer’s data automatically and irrevocably, namely the imprint page, the address data, the
                ID-card photos (on the web server and on the storage system under § 5 para. 4), the imprint profiles with all
                versions, the requests and the customer account. Log entries are kept only in pseudonymised form (without name and
                e-mail address); in addition, a deletion record without personal reference (date and number of the deleted records)
                is kept. Backup copies are overwritten in the regular backup cycle. A legal hold under § 5 para. 7 remains
                reserved; deletion then takes place as soon as the reason no longer applies.</li>
              <li><strong>Deletion upon other termination:</strong> If the contract ends in another way (in particular by
                termination by the Provider or by termination by the Customer in text form without a deletion request under
                para. 2), the Provider deletes the Customer’s data after the end of the contract as soon as they are no longer needed
                to comply with legal obligations or to assert or defend legal claims (§ 5 para. 7).</li>
            </ol>
          </section>

          <section id="p8">
            <h3>§ 8 Right of Withdrawal for Consumers</h3>
            <p>This section applies only to Customers who are consumers.</p>
            <ol>
              <li>Consumers may withdraw from the contract within 14 days without giving any reason. The withdrawal period is
                14 days from the day on which the contract is concluded (§ 3 para. 2).</li>
              <li>To exercise the right of withdrawal, the consumer must inform the Provider (<?= h($p['name']) ?>, <?= h($p['street']) ?>, <?= h($p['city']) ?>,
                e-mail <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>) of his or her decision to withdraw from the contract by means of an unequivocal statement (for example by letter
                or e-mail). The <a href="#anlage">model withdrawal form</a> may be used for this, but its use is not mandatory. To
                meet the withdrawal period, it is sufficient that the notification is sent before the period expires.</li>
              <li>In the event of an effective withdrawal, the Provider refunds payments already made without undue delay and no later than
                14 days from receipt of the withdrawal statement, using the same means of payment.</li>
              <li>If the consumer expressly requests that the Provider begin with the service (for example the review and approval of the
                imprint page) before the withdrawal period has expired, and the consumer subsequently withdraws, the consumer pays an
                appropriate amount for the service provided up to that point. If the Service is free of charge, no claim to payment arises.</li>
              <li>In the case of services, the right of withdrawal expires as soon as the Provider has fully performed the service, if
                the Provider began performance only after the consumer had expressly consented and had confirmed that he or she is
                aware that he or she loses the right of withdrawal upon full performance of the contract.</li>
            </ol>
          </section>

          <section id="p9">
            <h3>§ 9 Liability</h3>
            <ol>
              <li>The Provider is liable without limitation for damages resulting from injury to life, body or health and for
                intent and gross negligence.</li>
              <li>In relation to consumers, the Provider is otherwise liable in accordance with the statutory provisions.</li>
              <li>In relation to business customers, liability for slight negligence is excluded, as is liability for lost
                profit, indirect damage and consequential damage, to the extent permitted by law. Claims of recourse under § 12 of the
                Product Liability Act (Produkthaftungsgesetz) are excluded.</li>
              <li>The Provider is not liable for the Customer’s content, for linked third-party pages or for the consequences of
                incorrect or outdated details provided by the Customer. For interruptions due to force majeure or failures of third
                parties (for example hosting or e-mail providers), the Provider is liable only in the case of intent or gross
                negligence.</li>
              <li>The Customer indemnifies the Provider against claims of third parties (including reasonable costs of legal
                defence) that are based on unlawful content or details of the Customer or on a culpable breach of these Terms,
                to the extent that the Customer is responsible for the infringement.</li>
            </ol>
          </section>

          <section id="p10">
            <h3>§ 10 Amendments</h3>
            <ol>
              <li>The Provider may amend these Terms where there is an objective reason for doing so (for example a change in the law,
                further development of the Service or prevention of abuse). The Provider notifies the Customer of the amendment by e-mail at
                least four weeks before it takes effect and draws attention to the right to object and the consequences. If the
                Customer does not object in text form within this period, the amendments are deemed approved. In the event of an
                objection, both parties may terminate the contract as of the time the amendments take effect. For amendments to the
                main services and to the fee, the express consent of consumers is required.</li>
              <li>The Provider may in addition require the Customer to expressly consent in the customer portal to the then-current
                version. Until consent is given, the creation of new imprint profiles and the submission of changes may be
                suspended; the most recently approved imprint page remains online.</li>
              <li>The Provider may further develop and change the Service, to the extent that the Customer is not thereby placed at an
                unreasonable disadvantage. The Provider announces the discontinuation of the entire Service at least three months in
                advance.</li>
            </ol>
          </section>

          <section id="p11">
            <h3>§ 11 Final Provisions</h3>
            <ol>
              <li>Austrian law applies, excluding the UN Convention on Contracts for the International Sale of Goods and the
                conflict-of-laws rules of private international law. In relation to consumers, this choice of law applies only to the
                extent that it does not deprive them of the mandatory consumer protection provisions of the state in which the
                consumer has his or her habitual residence.</li>
              <li>For business customers, the court in Graz that is competent by subject matter has exclusive jurisdiction. For consumers,
                the statutory places of jurisdiction apply.</li>
              <li>Declarations and notices may be made in text form (for example by e-mail), unless the law requires a stricter
                form.</li>
              <li>The language of the contract is German. The English translation of these Terms is for information only; in the event of
                discrepancies, the German version prevails.</li>
              <li>In the event of disputes, the parties will endeavour to reach an amicable solution. Consumers may turn to a
                recognised consumer dispute resolution body; the Provider is not obliged to participate in a
                dispute resolution procedure.</li>
              <li>Should individual provisions of these Terms be or become invalid, the validity of the remaining provisions remains
                unaffected. The invalid provision is replaced by the legally permissible provision that comes closest to its economic
                purpose.</li>
            </ol>
          </section>

          <section id="anlage">
            <h3>Annex: Model Withdrawal Form</h3>
            <p>(If you wish to withdraw from the contract, you may complete this form and send it back to us.)</p>
            <p>To <?= h($p['name']) ?>, <?= h($p['street']) ?>, <?= h($p['city']) ?>, e-mail <a href="mailto:<?= h($p['mail_imprint']) ?>"><?= h($p['mail_imprint']) ?></a>:</p>
            <ul>
              <li>I hereby withdraw from the contract I concluded for the provision of the following service:
                Imprint Protection.</li>
              <li>Ordered on / account created on: ______________________</li>
              <li>Name of consumer(s): ______________________</li>
              <li>Address of consumer(s): ______________________</li>
              <li>Date and, if notified on paper, signature: ______________________</li>
            </ul>
          </section>

          <p class="terms-footnote">Questions about these Terms: <a href="mailto:<?= h($p['mail_contact']) ?>"><?= h($p['mail_contact']) ?></a>.
            <a href="/">Back to the home page</a> · <a href="/imprint-protection/">Imprint Protection</a> · <a href="/privacy/">Privacy</a></p>
        </div>
