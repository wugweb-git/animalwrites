import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "./ui/dialog";
import { Button } from "./ui/button";

interface LegalModalProps {
  triggerText?: string;
  triggerClassName?: string;
}

export function LegalModal({ triggerText = "Terms & Privacy", triggerClassName }: LegalModalProps) {
  return (
    <Dialog>
      <DialogTrigger asChild>
        <button className={triggerClassName}>
          {triggerText}
        </button>
      </DialogTrigger>
      <DialogContent className="w-[calc(100%-2rem)] sm:w-full sm:max-w-3xl h-[85vh] flex flex-col p-0 gap-0 bg-background overflow-hidden border-border/50 shadow-2xl">
        <DialogHeader className="p-6 pb-4 shrink-0 border-b border-border/10 bg-muted/5">
          <DialogTitle className="text-[length:var(--text-xl)] font-bold text-primary tracking-tight">Terms of Use & Privacy Policy</DialogTitle>
          <DialogDescription className="text-[length:var(--text-sm)] text-muted-foreground">
            Last Updated: December 2024
          </DialogDescription>
        </DialogHeader>
        
        <div className="flex-1 overflow-y-auto">
          <div className="p-6 md:p-8 space-y-8 text-foreground/80">
            <div className="text-[length:var(--text-sm)] leading-relaxed space-y-4 bg-muted/30 p-4 rounded-lg border border-border/50">
              <p>
                This Terms of Use and Privacy Policy ("Policy") governs the access to and use of this website ("Website") by visitors, users, contributors, and participants ("Users", "you", "your").
              </p>
              <p>
                By accessing, browsing, downloading materials from, or using any features of this Website, you expressly consent to the terms set out herein.
              </p>
            </div>

            <div className="space-y-8">
              {/* Section 1 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">01.</span>
                  Purpose and Nature of the Website
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">1.1 This Website functions as a public-interest civic platform intended to:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Facilitate lawful democratic expression and protest by concerned citizens;</li>
                    <li>Provide informational guidance and downloadable templates for writing letters addressed to the Hon'ble Chief Justice of India regarding issues relating to judicial and administrative handling of stray dog-related matters;</li>
                    <li>Enable assessment of public engagement through non-intrusive data collection.</li>
                  </ul>
                  <p>1.2 The Website does not provide legal advice, does not claim to represent any authority, and does not initiate litigation on behalf of users.</p>
                </div>
              </section>

              {/* Section 2 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">02.</span>
                  Eligibility and User Responsibility
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">2.1 By using the Website, you confirm that:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>You are competent to contract under Indian law;</li>
                    <li>Your use is lawful, peaceful, and non-violent;</li>
                    <li>You will not misuse the platform for hate speech, harassment, incitement, or unlawful activity.</li>
                  </ul>
                  <p>2.2 The Website merely provides guidance and templates. Any letter sent using the templates is done entirely at your own discretion and responsibility.</p>
                </div>
              </section>

              {/* Section 3 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">03.</span>
                  Data Collected
                </h3>
                <div className="space-y-4 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p>The Website may collect the following categories of data:</p>
                  
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="bg-muted/20 p-4 rounded-lg">
                      <h4 className="font-semibold text-foreground mb-2 text-xs uppercase tracking-wider text-accent">3.1 Automatically Collected Data</h4>
                      <ul className="list-disc pl-5 space-y-1 marker:text-accent/50">
                        <li>IP address (temporarily and/or anonymised)</li>
                        <li>Browser type, device type, operating system</li>
                        <li>Pages accessed, duration of visit</li>
                        <li>Downloads of templates or resources</li>
                        <li>Date and time of access</li>
                      </ul>
                    </div>
                    
                    <div className="bg-muted/20 p-4 rounded-lg">
                      <h4 className="font-semibold text-foreground mb-2 text-xs uppercase tracking-wider text-accent">3.2 Voluntarily Shared Data</h4>
                      <ul className="list-disc pl-5 space-y-1 marker:text-accent/50">
                        <li>State and/or district (if submitted)</li>
                        <li>Confirmation of having posted letters</li>
                        <li>Uploaded photographs or confirmations of dispatch (optional)</li>
                        <li>Participation in WhatsApp or other messaging groups (voluntary)</li>
                      </ul>
                    </div>
                  </div>

                  <div className="bg-primary/5 p-4 rounded-lg border border-primary/10">
                    <h4 className="font-semibold text-foreground mb-2 text-xs uppercase tracking-wider">3.3 Non-Collection of Sensitive Personal Data</h4>
                    <p className="mb-2">The Website does not intentionally collect:</p>
                    <ul className="list-disc pl-5 space-y-1 marker:text-primary/50">
                      <li>Aadhaar numbers</li>
                      <li>Financial information</li>
                      <li>Biometric data</li>
                      <li>Health data</li>
                      <li>Political affiliation details beyond general civic participation</li>
                    </ul>
                  </div>
                </div>
              </section>

              {/* Section 4 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">04.</span>
                  Use of Data
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">4.1 Data collected is used for the following lawful purposes:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Measuring public engagement and participation;</li>
                    <li>Preparing aggregate and anonymised and non-anonymised reports;</li>
                    <li>Publishing numerical data (including state-wise or district-wise statistics);</li>
                    <li>Academic, journalistic, advocacy, and public-interest reporting;</li>
                    <li>Submission before constitutional courts or authorities as part of affidavits, annexures, or reports.</li>
                  </ul>
                  <p>4.2 By using the website users consent to be identified in name, however, the same will be done for limited purposes central to the theme of the website and not for any commercial usage.</p>
                </div>
              </section>

              {/* Section 5 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">05.</span>
                  Aggregation, Anonymisation and Publication
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">5.1 Users expressly consent to:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Aggregation of their usage data with other users;</li>
                    <li>Use of aggregated data in public documents, media publications, and court filings.</li>
                  </ul>
                  <p className="font-medium text-foreground mt-4">5.2 Where photographs or confirmations of letter dispatch are shared:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Such material may be shared within closed messaging groups or internal compilations;</li>
                    <li>It may be used for aggregating data for the purposes delineated above.</li>
                  </ul>
                </div>
              </section>

              {/* Section 6 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">06.</span>
                  Consent and Legal Basis
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">6.1 Consent is obtained:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>By continued use of the Website;</li>
                    <li>By voluntary submission of information;</li>
                    <li>By opting into WhatsApp or other communication groups.</li>
                  </ul>
                  <p className="bg-accent/10 p-3 rounded-md text-foreground border border-accent/20">
                    6.2 This Policy constitutes explicit consent under the Digital Personal Data Protection Act, 2023.
                  </p>
                </div>
              </section>

              {/* Section 7 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">07.</span>
                  User Rights (DPDP Act, 2023)
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">Users have the right to:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Seek confirmation of whether their data is being processed;</li>
                    <li>Request correction or erasure of personal data;</li>
                    <li>Withdraw consent prospectively (subject to lawful use already undertaken).</li>
                  </ul>
                  <p className="mt-4 pt-4 border-t border-dashed border-border">
                    Requests may be sent to: <a href="mailto:contact@animalwrites.in" className="text-accent hover:underline font-medium">contact@animalwrites.in</a>
                  </p>
                </div>
              </section>

              {/* Section 8 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">08.</span>
                  Third-Party Services
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">The Website may use:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Analytics tools;</li>
                    <li>Messaging platforms (e.g., WhatsApp);</li>
                    <li>Hosting and security services.</li>
                  </ul>
                  <p>Each is governed by its own privacy policies. The Website assumes no responsibility for third-party data practices.</p>
                </div>
              </section>

              {/* Section 9 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">09.</span>
                  Intellectual Property
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">9.1 All content, templates, guides, and compilations are:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Original works protected under the Copyright Act, 1957;</li>
                    <li>Provided for personal, non-commercial civic use.</li>
                  </ul>
                  <p>9.2 Templates may be downloaded, printed, signed, and posted without alteration of attribution, but may not be sold or commercially exploited.</p>
                </div>
              </section>

              {/* Section 10 */}
              <section className="space-y-3">
                <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                  <span className="text-accent text-sm">10.</span>
                  Disclaimer
                </h3>
                <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                  <p className="font-medium text-foreground">10.1 The Website:</p>
                  <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                    <li>Does not guarantee any outcome from letter writing;</li>
                    <li>Does not influence judicial processes;</li>
                    <li>Does not claim endorsement by any authority.</li>
                  </ul>
                  <p>10.2 All actions taken using the Website are voluntary civic expressions.</p>
                </div>
              </section>

              {/* Section 11, 12 */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <section className="space-y-3">
                  <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                    <span className="text-accent text-sm">11.</span>
                    Limitation of Liability
                  </h3>
                  <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                    <p className="font-medium text-foreground">The Website owner/operator shall not be liable for:</p>
                    <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                      <li>Any misuse of templates;</li>
                      <li>Any consequence of letters sent by users;</li>
                      <li>Any indirect or consequential loss.</li>
                    </ul>
                  </div>
                </section>

                <section className="space-y-3">
                  <h3 className="text-[length:var(--text-lg)] font-bold text-primary flex items-baseline gap-2">
                    <span className="text-accent text-sm">12.</span>
                    Indemnity
                  </h3>
                  <div className="space-y-3 pl-0 md:pl-7 text-[length:var(--text-sm)] text-muted-foreground leading-relaxed">
                    <p className="font-medium text-foreground">Users agree to indemnify and hold harmless the Website owner from claims arising from:</p>
                    <ul className="list-disc pl-5 space-y-2 marker:text-accent">
                      <li>User-generated content;</li>
                      <li>Unlawful or defamatory use of templates;</li>
                      <li>Breach of these terms.</li>
                    </ul>
                  </div>
                </section>
              </div>

              {/* Section 13, 14 */}
              <section className="space-y-4 pt-6 border-t border-border">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                  <div className="space-y-2">
                    <h3 className="text-[length:var(--text-base)] font-bold text-primary">13. Governing Law and Jurisdiction</h3>
                    <p className="text-[length:var(--text-sm)] text-muted-foreground">
                      This Policy shall be governed by Indian law. Courts at <strong>New Delhi</strong>, India shall have exclusive jurisdiction.
                    </p>
                  </div>
                  <div className="space-y-2">
                    <h3 className="text-[length:var(--text-base)] font-bold text-primary">14. Changes to Policy</h3>
                    <p className="text-[length:var(--text-sm)] text-muted-foreground">
                      The Policy may be updated periodically. Continued use constitutes acceptance of revised terms.
                    </p>
                  </div>
                </div>
              </section>
            </div>
          </div>
        </div>
        
        <div className="p-4 border-t border-border flex justify-end shrink-0 bg-muted/5">
          <DialogTrigger asChild>
            <Button variant="outline" className="border-primary/20 hover:border-primary/50 hover:bg-primary/5">Close</Button>
          </DialogTrigger>
        </div>
      </DialogContent>
    </Dialog>
  );
}
