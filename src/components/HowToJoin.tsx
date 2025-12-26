import { FileText, MapPin, Send, Users } from "lucide-react";
import { motion } from "motion/react";
import { Button } from "./ui/button";
import { ImageWithFallback } from "./figma/ImageWithFallback";

export function HowToJoin() {
  const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

  const steps = [
    {
      number: "1",
      icon: FileText,
      title: "Write Your Letter",
      description: "Address it to the Chief Justice & Supreme Court. Share your voice, hope, reasoning.",
    },
    {
      number: "2",
      icon: MapPin,
      title: "Post It on 29 Nov",
      description: "Walk to your nearest GPO. Drop your letter. Stand with thousands.",
    },
    {
      number: "3",
      icon: Send,
      title: "Be Part of History",
      description: "Your letter joins India's largest coordinated letter-petition movement.",
    },
  ];

  return (
    <section 
      id="how-to-join" 
      className="py-16 md:py-24 bg-white"
      aria-labelledby="how-to-join-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="how-to-join-heading" className="text-primary mb-4 md:mb-6">
              How You Can Join
            </h2>
            <p className="text-muted-foreground max-w-2xl mx-auto">
              Three simple steps to make your voice heard
            </p>
          </motion.div>

          <div className="space-y-6 md:space-y-8 mb-12 md:mb-16">
            {steps.map((step, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, x: -30 }}
                whileInView={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.15, duration: 0.5 }}
                viewport={{ once: true }}
              >
                <motion.div
                  className="bg-muted rounded-[var(--radius-2xl)] p-6 md:p-10 hover:shadow-[var(--elevation-lg)] transition-shadow cursor-pointer"
                  whileHover={{ scale: 1.02, backgroundColor: "var(--surface-warm)" }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <div className="flex flex-col sm:flex-row items-start gap-4 md:gap-6">
                    <div className="flex-shrink-0 mx-auto sm:mx-0">
                      <motion.div
                        className="w-16 h-16 md:w-20 md:h-20 rounded-[var(--radius-full)] bg-primary text-white flex items-center justify-center shadow-[var(--elevation-lg)]"
                        whileHover={{ 
                          rotate: [0, -10, 10, -10, 0],
                          scale: 1.1 
                        }}
                        transition={{ duration: 0.5 }}
                      >
                        {step.number}
                      </motion.div>
                    </div>
                    <div className="flex-1 text-center sm:text-left">
                      <div className="flex flex-col sm:flex-row items-center sm:items-start gap-2 sm:gap-3 mb-3">
                        <motion.div
                          whileHover={{ scale: 1.2, rotate: 10 }}
                          transition={{ type: "spring", stiffness: 400 }}
                        >
                          <step.icon className="w-6 h-6 text-primary" aria-hidden="true" />
                        </motion.div>
                        <h3 className="text-primary">{step.title}</h3>
                      </div>
                      <p className="text-muted-foreground leading-relaxed">{step.description}</p>
                    </div>
                  </div>
                </motion.div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
            className="bg-[var(--surface-warm)] rounded-[var(--radius-2xl)] p-6 md:p-12 border-2 border-primary/20 hover:border-primary/40 transition-colors"
          >
            <div className="flex flex-col md:flex-row items-center gap-6 md:gap-8">
              <motion.div
                className="flex-shrink-0"
                whileHover={{ rotate: 360, scale: 1.1 }}
                transition={{ duration: 0.6 }}
              >
                <div className="bg-card rounded-[var(--radius-full)] p-4 md:p-6 shadow-[var(--elevation-lg)]">
                  <Users className="w-10 h-10 md:w-12 md:h-12 text-primary" aria-hidden="true" />
                </div>
              </motion.div>
              <div className="flex-1 text-center md:text-left">
                <h3 className="text-primary mb-2">Want to Coordinate a Group?</h3>
                <p className="text-muted-foreground mb-4 md:mb-0">
                  Lead the movement in your city and inspire others to participate
                </p>
              </div>
              <motion.div
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button
                  onClick={() => scrollToSection("volunteer")}
                  className="bg-primary hover:bg-[var(--primary-hover)] text-white px-6 md:px-8 py-5 md:py-6 focus:ring-2 focus:ring-primary focus:ring-offset-2"
                  aria-label="Navigate to volunteer signup section"
                >
                  Join Leadership Circle
                </Button>
              </motion.div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}