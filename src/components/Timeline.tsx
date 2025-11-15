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
      description: "Thousands walk to post offices across India to mail letters to the Supreme Court",
      status: "upcoming" as const,
      icon: Calendar,
    },
    {
      date: "December 2025",
      title: "Follow-up & Awareness",
      description: "Continue spreading awareness and document the movement's impact",
      status: "upcoming" as const,
      icon: Clock,
    },
  ];

  return (
    <section className="py-16 md:py-24 bg-white" aria-labelledby="timeline-heading">
      <div className="container mx-auto px-4">
        <div className="max-w-5xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="timeline-heading" className="text-2xl md:text-4xl text-[#333333] mb-4 font-bold">
              Campaign Timeline
            </h2>
            <p className="text-[#666666] text-base md:text-lg">
              Key milestones in our journey toward compassionate coexistence
            </p>
          </motion.div>

          <div className="relative">
            {/* Timeline line */}
            <div className="absolute left-4 md:left-1/2 top-0 bottom-0 w-0.5 bg-[#F59E0B]/20" aria-hidden="true" />

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
                    className="absolute left-4 md:left-1/2 w-8 h-8 -ml-4 bg-white border-4 border-[#F59E0B] rounded-full z-10 flex items-center justify-center"
                    whileHover={{ scale: 1.2 }}
                    transition={{ type: "spring", stiffness: 400 }}
                  >
                    {milestone.status === "completed" && (
                      <div className="w-3 h-3 bg-[#F59E0B] rounded-full" />
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
                          ? "from-[#FFF8F3] to-white border-[#F59E0B]"
                          : "from-white to-[#FFF8F3] border-[#F59E0B]/30"
                      } border-2 rounded-2xl p-5 md:p-6 shadow-md hover:shadow-lg transition-all`}
                    >
                      <div className="flex items-start gap-3 mb-3">
                        <motion.div
                          className={`p-2 rounded-full ${
                            milestone.status === "completed"
                              ? "bg-[#F59E0B]"
                              : "bg-[#F59E0B]/20"
                          }`}
                          whileHover={{ rotate: 360 }}
                          transition={{ duration: 0.6 }}
                        >
                          <milestone.icon
                            className={`w-5 h-5 ${
                              milestone.status === "completed"
                                ? "text-white"
                                : "text-[#F59E0B]"
                            }`}
                            aria-hidden="true"
                          />
                        </motion.div>
                        <div className="flex-1">
                          <p
                            className={`text-sm ${
                              milestone.status === "completed"
                                ? "text-[#F59E0B]"
                                : "text-[#666666]"
                            } mb-1`}
                          >
                            {milestone.date}
                          </p>
                          <h3 className="text-[#333333] text-base md:text-lg mb-2 font-bold">
                            {milestone.title}
                          </h3>
                          <p className="text-[#666666] text-sm leading-relaxed">
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
                          className="h-1 bg-gradient-to-r from-[#F59E0B] to-[#D97706] rounded-full mt-4"
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
            className="mt-12 text-center bg-[#FFF8F3] rounded-2xl p-6 md:p-8 border-2 border-[#F59E0B]/20"
          >
            <p className="text-[#333333] text-base md:text-lg">
              <strong className="text-[#F59E0B]">Be part of history!</strong> Join us on November 29th and help create a movement that will be remembered for generations.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}