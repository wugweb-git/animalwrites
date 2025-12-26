import { useState } from "react";
import { Users, Mail, MapPin, CheckCircle2, AlertCircle } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Label } from "./ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "./ui/select";
import { motion, AnimatePresence } from "motion/react";
import { Confetti } from "./Confetti";
import { projectId, publicAnonKey } from "../utils/supabase/info";

export function VolunteerSignup() {
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    city: "",
    role: "",
  });
  const [consent, setConsent] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [showConfetti, setShowConfetti] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setErrorMessage("");

    if (!consent) {
      setErrorMessage("Please accept the consent to proceed");
      setIsSubmitting(false);
      return;
    }

    try {
      const response = await fetch(
        `https://${projectId}.supabase.co/functions/v1/make-server-7a8516f4/volunteer-signup`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "Authorization": `Bearer ${publicAnonKey}`,
          },
          body: JSON.stringify(formData),
        }
      );

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error || "Failed to submit volunteer signup");
      }

      console.log("Volunteer signup successful:", result);
      setSubmitted(true);
      setShowConfetti(true);
      
      setTimeout(() => {
        setShowConfetti(false);
      }, 3000);
      
      setTimeout(() => {
        setSubmitted(false);
        setFormData({ name: "", email: "", city: "", role: "" });
        setConsent(false);
      }, 5000);
    } catch (error) {
      console.error("Error submitting volunteer signup:", error);
      setErrorMessage(error instanceof Error ? error.message : "Failed to submit. Please try again.");
    } finally {
      setIsSubmitting(false);
    }
  };

  const roles = [
    { value: "coordinator", label: "City Coordinator - Lead the movement in your city" },
    { value: "social", label: "Social Media Volunteer - Help spread the word online" },
    { value: "writer", label: "Writing Helper - Assist others in writing their letters" },
    { value: "logistics", label: "Logistics Support - Help organize GPO visits" },
    { value: "translator", label: "Translator - Help translate materials to regional languages" },
    { value: "general", label: "General Volunteer - Help wherever needed" },
  ];

  return (
    <section 
      id="volunteer" 
      className="py-16 md:py-24 bg-gradient-to-b from-[var(--surface-warm)] to-background"
      aria-labelledby="volunteer-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <div className="inline-flex items-center gap-3 mb-4">
              <motion.div
                animate={{ rotate: [0, 10, -10, 0] }}
                transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
              >
                <Users className="w-7 h-7 md:w-8 md:h-8 text-foreground" aria-hidden="true" />
              </motion.div>
              <h2 id="volunteer-heading" className="text-2xl md:text-4xl text-foreground font-bold">Volunteer With Us</h2>
            </div>
            <p className="text-muted-foreground text-base md:text-lg max-w-3xl mx-auto leading-relaxed">
              Want to do more? Join our volunteer team and help coordinate this movement in your city. 
              Together, we can reach every corner of India.
            </p>
          </motion.div>

          <div className="grid md:grid-cols-2 gap-6 md:gap-8">
            <div className="space-y-6">
              <motion.div
                initial={{ opacity: 0, x: -20 }}
                whileInView={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.5 }}
                viewport={{ once: true }}
              >
                <Card className="border-2 border-accent/20 hover:border-accent transition-colors bg-card">
                  <CardHeader>
                    <CardTitle className="text-accent-hover flex items-center gap-2 text-lg md:text-xl font-bold">
                      <MapPin className="w-5 h-5" aria-hidden="true" />
                      City Coordinators Needed
                    </CardTitle>
                    <CardDescription className="text-sm md:text-base text-muted-foreground">
                      Lead the movement in your city and inspire others to participate
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3 text-muted-foreground text-sm md:text-base">
                    {[
                      "Organize local meetups and GPO visits",
                      "Share information about the campaign in your community",
                      "Help people write and mail their letters",
                      "Document the movement through photos and stories"
                    ].map((item, index) => (
                      <motion.div
                        key={index}
                        className="flex items-start gap-2"
                        initial={{ opacity: 0, x: -10 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.3, delay: index * 0.1 }}
                        viewport={{ once: true }}
                      >
                        <span className="text-foreground text-lg" aria-hidden="true">✓</span>
                        <span>{item}</span>
                      </motion.div>
                    ))}
                  </CardContent>
                </Card>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, x: -20 }}
                whileInView={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.5, delay: 0.2 }}
                viewport={{ once: true }}
              >
                <Card className="border-2 border-accent/20 hover:border-accent transition-colors bg-card">
                  <CardHeader>
                    <CardTitle className="text-accent-hover flex items-center gap-2 text-lg md:text-xl font-bold">
                      <Mail className="w-5 h-5" aria-hidden="true" />
                      Other Ways to Help
                    </CardTitle>
                    <CardDescription className="text-sm md:text-base text-muted-foreground">
                      Every role is crucial to our success
                    </CardDescription>
                  </CardHeader>
                  <CardContent className="space-y-3 text-muted-foreground text-sm md:text-base">
                    {[
                      "Social media management and content creation",
                      "Translation to regional languages",
                      "Design posters and campaign materials",
                      "Connect with local animal welfare groups"
                    ].map((item, index) => (
                      <motion.div
                        key={index}
                        className="flex items-start gap-2"
                        initial={{ opacity: 0, x: -10 }}
                        whileInView={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.3, delay: index * 0.1 }}
                        viewport={{ once: true }}
                      >
                        <span className="text-foreground text-lg" aria-hidden="true">✓</span>
                        <span>{item}</span>
                      </motion.div>
                    ))}
                  </CardContent>
                </Card>
              </motion.div>
            </div>

            <motion.div
              initial={{ opacity: 0, x: 20 }}
              whileInView={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.5, delay: 0.3 }}
              viewport={{ once: true }}
            >
              <Card className="border-2 border-accent/30 hover:border-accent transition-colors bg-card">
                <CardHeader>
                  <CardTitle className="text-accent-hover text-lg md:text-xl font-bold">Sign Up to Volunteer</CardTitle>
                  <CardDescription className="text-sm md:text-base text-muted-foreground">
                    Fill out this form and we'll get in touch with you
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <AnimatePresence mode="wait">
                    {submitted ? (
                      <motion.div
                        key="success"
                        initial={{ opacity: 0, scale: 0.8 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.8 }}
                        transition={{ duration: 0.5 }}
                        className="text-center py-8"
                      >
                        <motion.div
                          animate={{ scale: [1, 1.2, 1] }}
                          transition={{ duration: 0.6, repeat: 2 }}
                        >
                          <CheckCircle2 className="w-16 h-16 text-green-600 mx-auto mb-4" />
                        </motion.div>
                        <h3 className="text-green-700 text-xl mb-2">Thank You!</h3>
                        <p className="text-muted-foreground text-sm md:text-base">
                          Your volunteer registration has been received. We'll reach out to you soon with more details.
                        </p>
                      </motion.div>
                    ) : (
                      <motion.form
                        key="form"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        onSubmit={handleSubmit}
                        className="space-y-5 md:space-y-6"
                      >
                        <div className="space-y-2">
                          <Label htmlFor="name" className="text-base md:text-base text-foreground">Full Name *</Label>
                          <Input
                            id="name"
                            type="text"
                            required
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            placeholder="Enter your full name"
                            className="border-2 border-border focus:border-accent transition-colors h-14 text-lg bg-input-background"
                            aria-required="true"
                          />
                        </div>

                        <div className="space-y-2">
                          <Label htmlFor="email" className="text-base md:text-base text-foreground">Email Address *</Label>
                          <Input
                            id="email"
                            type="email"
                            required
                            value={formData.email}
                            onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                            placeholder="your.email@example.com"
                            className="border-2 border-border focus:border-accent transition-colors h-14 text-lg bg-input-background"
                            aria-required="true"
                          />
                        </div>

                        <div className="space-y-2">
                          <Label htmlFor="city" className="text-base md:text-base text-foreground">City *</Label>
                          <Input
                            id="city"
                            type="text"
                            required
                            value={formData.city}
                            onChange={(e) => setFormData({ ...formData, city: e.target.value })}
                            placeholder="Your city name"
                            className="border-2 border-border focus:border-accent transition-colors h-14 text-lg bg-input-background"
                            aria-required="true"
                          />
                        </div>

                        <div className="space-y-2">
                          <Label htmlFor="role" className="text-base md:text-base text-foreground">How Would You Like to Help? *</Label>
                          <Select
                            required
                            value={formData.role}
                            onValueChange={(value) => setFormData({ ...formData, role: value })}
                          >
                            <SelectTrigger 
                              className="border-2 border-border focus:border-accent transition-colors h-14 text-lg bg-input-background"
                              aria-label="Select volunteer role"
                            >
                              <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                              {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                  {role.label}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>

                        <div className="p-5 md:p-4 bg-[var(--surface-warm)] rounded-lg border border-accent/20">
                          <label htmlFor="consent" className="flex items-start gap-4 cursor-pointer group">
                            <input
                              type="checkbox"
                              id="consent"
                              checked={consent}
                              onChange={(e) => setConsent(e.target.checked)}
                              className="mt-1 h-6 w-6 text-accent focus:ring-accent focus:ring-offset-2 border-border rounded cursor-pointer flex-shrink-0"
                              required
                              aria-required="true"
                            />
                            <span className="text-muted-foreground leading-relaxed group-hover:text-foreground transition-colors text-[13px]">
                              I consent to being contacted by the Animal Writes campaign team regarding volunteer opportunities and campaign updates. I understand my information will be used solely for this campaign and will be kept confidential.
                            </span>
                          </label>
                        </div>

                        <motion.div
                          whileHover={{ scale: 1.02 }}
                          whileTap={{ scale: 0.98 }}
                        >
                          <Button
                            type="submit"
                            disabled={!consent || isSubmitting}
                            className="w-full bg-primary hover:bg-primary/90 text-primary-foreground py-7 text-lg focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed touch-manipulation"
                          >
                            {isSubmitting ? "Submitting..." : "Join as Volunteer"}
                          </Button>
                        </motion.div>

                        {errorMessage && (
                          <motion.div
                            initial={{ opacity: 0, y: -10 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-lg"
                          >
                            <AlertCircle className="w-4 h-4 text-destructive flex-shrink-0 mt-0.5" />
                            <p className="text-sm text-destructive">{errorMessage}</p>
                          </motion.div>
                        )}
                      </motion.form>
                    )}
                  </AnimatePresence>
                </CardContent>
              </Card>
            </motion.div>
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="mt-12 bg-[var(--surface-warm)] rounded-2xl p-6 md:p-8 border-2 border-accent/20"
          >
            <div className="text-center">
              <p className="text-foreground text-base md:text-lg mb-4">
                <strong className="text-accent-hover">Can't volunteer but want to help?</strong>
              </p>
              <p className="text-muted-foreground text-sm md:text-base">
                Share this campaign with everyone you know, write your letter, and encourage others to participate. 
                Every action, no matter how small, creates ripples of change.
              </p>
            </div>
          </motion.div>
        </div>
      </div>
      {showConfetti && <Confetti />}
    </section>
  );
}