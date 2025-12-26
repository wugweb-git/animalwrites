import { motion } from "motion/react";
import { Calendar, CheckCircle2, Clock, Sparkles } from "lucide-react";

export function Timeline() {
  const milestones = [
    {
      date: "November 1, 2025",
      title: "Campaign Launch",
      description: "Animal Writes movement officially announced across social media",
      status: "completed" as const,
      icon: Sparkles,
    },
    {
      date: "November 10, 2025",
      title: "10,000 Citizens Committed",
      description: "First major milestone reached with supporters across 50+ cities",
      status: "completed" as const,
      icon: CheckCircle2,
    },
    {
      date: "November 20, 2025",
      title: "Regional Coordinators Active",
      description: "100+ volunteer coordinators organizing local movements in their cities",
      status: "completed" as const,
      icon: CheckCircle2,
    },
    {
      date: "November 29, 2025",
      title: "The Big Day - National GPO Walk",
      description: "Thousands walked to post offices across India to mail letters to the Supreme Court",
      status: "completed" as const,
      icon: CheckCircle2,
    },
    {
      date: "December 2, 2025",
      title: "2.5 Lakhs Letters Delivered!",
      description: "Historic achievement - Over 2,50,000 handwritten letters successfully sent to Supreme Court",
      status: "completed" as const,
      icon: CheckCircle2,
    },
    {
      date: "December 2025 - Ongoing",
      title: "Phase 2.0: Pashu Bachao Vote Badhao",
      description: "Electoral engagement and voter registration campaign to amplify animal welfare through democratic participation",
      status: "current" as const,
      icon: Calendar,
    },
    {
      date: "2026 & Beyond",
      title: "Sustained Advocacy & Impact",
      description: "Continuing awareness campaigns, policy monitoring, and building a compassionate India",
      status: "upcoming" as const,
      icon: Clock,
    },
  ];

  return (
    <section id="timeline" className="py-16 md:py-24 bg-white" aria-labelledby="timeline-heading">
      <div className="container mx-auto px-4">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="timeline-heading" className="text-2xl md:text-4xl text-foreground mb-4 font-bold">
              Campaign Timeline
            </h2>
            <p className="text-muted-foreground text-base md:text-lg">
              Key milestones in our journey toward compassionate coexistence
            </p>
          </motion.div>

          <div className="relative">
            {/* Timeline line */}
            <div className="absolute left-4 md:left-1/2 top-0 bottom-0 w-0.5 bg-accent/20" aria-hidden="true" />

            <div className="space-y-8 md:space-y-12">
              {milestones.map((milestone, index) => (
                <motion.div
                  key={index}
                  initial={{ opacity: 0, y: 30 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.5, delay: index * 0.1 }}
                  viewport={{ once: true }}
                  className={`relative flex items-center ${
                    index % 2 === 0 ? "md:flex-row" : "md:flex-row-reverse"
                  } flex-row`}
                >
                  {/* Timeline dot */}
                  <motion.div
                    className="absolute left-4 md:left-1/2 w-8 h-8 -ml-4 bg-white border-4 border-accent rounded-full z-10 flex items-center justify-center"
                    whileHover={{ scale: 1.2 }}
                    transition={{ type: "spring", stiffness: 400 }}
                  >
                    {milestone.status === "completed" && (
                      <div className="w-3 h-3 bg-accent rounded-full" />
                    )}
                  </motion.div>

                  {/* Content card */}
                  <motion.div
                    className={`w-full md:w-[calc(50%-2rem)] ml-12 md:ml-0 ${
                      index % 2 === 0 ? "md:mr-auto md:pr-8" : "md:ml-auto md:pl-8"
                    }`}
                    whileHover={{ y: -5 }}
                    transition={{ type: "spring", stiffness: 300 }}
                  >
                    <div
                      className={`bg-gradient-to-br ${
                        milestone.status === "completed"
                          ? "from-surface-warm to-white border-accent"
                          : milestone.status === "current"
                          ? "from-accent/10 to-white border-accent shadow-lg"
                          : "from-white to-surface-warm border-accent/30"
                      } border-2 rounded-2xl p-5 md:p-6 shadow-md hover:shadow-lg transition-all`}
                    >
                      <div className="flex items-start gap-3 mb-3">
                        <motion.div
                          className={`p-2 rounded-full ${
                            milestone.status === "completed" || milestone.status === "current"
                              ? "bg-accent"
                              : "bg-accent/20"
                          }`}
                          whileHover={{ rotate: 360 }}
                          transition={{ duration: 0.6 }}
                        >
                          <milestone.icon
                            className={`w-5 h-5 ${
                              milestone.status === "completed" || milestone.status === "current"
                                ? "text-white"
                                : "text-accent-hover"
                            }`}
                            aria-hidden="true"
                          />
                        </motion.div>
                        <div className="flex-1">
                          <p
                            className={`text-sm ${
                              milestone.status === "completed" || milestone.status === "current"
                                ? "text-accent-hover"
                                : "text-muted-foreground"
                            } mb-1`}
                          >
                            {milestone.date}
                          </p>
                          <h3 className="text-foreground text-base md:text-lg mb-2 font-bold">
                            {milestone.title}
                          </h3>
                          <p className="text-muted-foreground text-sm leading-relaxed">
                            {milestone.description}
                          </p>
                        </div>
                      </div>

                      {milestone.status === "completed" && (
                        <motion.div
                          initial={{ width: 0 }}
                          whileInView={{ width: "100%" }}
                          transition={{ duration: 0.8, delay: 0.3 }}
                          viewport={{ once: true }}
                          className="h-1 bg-gradient-to-r from-accent to-accent-hover rounded-full mt-4"
                          aria-label="Completed milestone indicator"
                        />
                      )}
                    </div>
                  </motion.div>
                </motion.div>
              ))}
            </div>
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.5 }}
            viewport={{ once: true }}
            className="mt-12 text-center bg-gradient-to-r from-accent to-accent-hover rounded-2xl p-6 md:p-8 shadow-xl"
          >
            <p className="text-white text-base md:text-lg">
              <strong>Historic achievement unlocked! 🎉</strong> Over 2.5 lakh letters delivered to the Supreme Court. Now join Phase 2.0 - <strong>Pashu Bachao Vote Badhao</strong> to amplify animal welfare through electoral engagement!
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}